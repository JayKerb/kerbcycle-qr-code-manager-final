import json
import logging
import os
from typing import Any, Dict, List

import httpx
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel

app = FastAPI()
logger = logging.getLogger("kerbcycle_render_ai")


class AiRequest(BaseModel):
    task: str
    data: Dict[str, Any]


def _build_prompt(task: str, payload: Dict[str, Any]) -> str:
    contracts: Dict[str, Dict[str, Any]] = {
        "pickup_summary": {
            "summary": "string",
            "highlights": ["string"],
            "issues": ["string"],
        },
        "qr_exceptions": {
            "exceptions": [
                {
                    "code": "string",
                    "reason": "string",
                    "severity": "low|medium|high",
                }
            ]
        },
        "draft_template": {
            "subject": "string",
            "message": "string",
        },
    }
    return (
        "You are a municipal operations assistant. "
        "Return ONLY valid JSON. Do not include markdown, code fences, explanations, or extra keys.\n"
        f"Task: {task}\n"
        f"Required JSON schema: {json.dumps(contracts[task])}\n"
        f"Input payload: {json.dumps(payload)}"
    )


def _validate_contract(task: str, value: Any) -> bool:
    if not isinstance(value, dict):
        return False

    if task == "pickup_summary":
        return (
            isinstance(value.get("summary"), str)
            and isinstance(value.get("highlights"), list)
            and all(isinstance(item, str) for item in value.get("highlights", []))
            and isinstance(value.get("issues"), list)
            and all(isinstance(item, str) for item in value.get("issues", []))
        )

    if task == "qr_exceptions":
        exceptions = value.get("exceptions")
        if not isinstance(exceptions, list):
            return False
        for item in exceptions:
            if not isinstance(item, dict):
                return False
            if not isinstance(item.get("code"), str) or not isinstance(item.get("reason"), str):
                return False
            if item.get("severity") not in {"low", "medium", "high"}:
                return False
        return True

    if task == "draft_template":
        return isinstance(value.get("subject"), str) and isinstance(value.get("message"), str)

    return False


def _call_provider(prompt: str) -> str:
    provider_url = os.getenv("AI_PROVIDER_URL", "")
    model = os.getenv("AI_PROVIDER_MODEL", "")
    api_key = os.getenv("AI_PROVIDER_API_KEY", "")
    if not provider_url or not model:
        raise RuntimeError("AI provider is not configured")

    payload = {
        "model": model,
        "messages": [{"role": "user", "content": prompt}],
        "temperature": 0,
    }
    headers = {"Content-Type": "application/json"}
    if api_key:
        headers["Authorization"] = f"Bearer {api_key}"

    response = httpx.post(provider_url, json=payload, headers=headers, timeout=30)
    response.raise_for_status()
    body = response.json()

    choices: List[Dict[str, Any]] = body.get("choices", []) if isinstance(body, dict) else []
    if choices and isinstance(choices[0], dict):
        message = choices[0].get("message", {})
        if isinstance(message, dict) and isinstance(message.get("content"), str):
            return message["content"]

    if isinstance(body, dict) and isinstance(body.get("output"), str):
        return body["output"]

    raise RuntimeError("AI provider response did not contain model output")


@app.post("/ai")
def handle_ai_request(request: AiRequest, x_api_key: str = Header(default="")) -> Dict[str, Any]:
    expected_key = os.getenv("RENDER_API_KEY", "")
    if not expected_key or x_api_key != expected_key:
        raise HTTPException(status_code=401, detail={"error": "Unauthorized"})

    task = request.task
    if task not in {"pickup_summary", "qr_exceptions", "draft_template"}:
        raise HTTPException(status_code=400, detail={"error": "Unsupported task", "task": task})

    try:
        prompt = _build_prompt(task, request.data)
        raw_output = _call_provider(prompt)
        parsed = json.loads(raw_output)
    except json.JSONDecodeError:
        logger.warning("ai task=%s status=failure reason=invalid_json", task)
        raise HTTPException(
            status_code=422,
            detail={"error": "Invalid model JSON output", "meta": {"task": task, "valid_json": False}},
        )
    except Exception as exc:
        logger.warning("ai task=%s status=failure reason=%s", task, str(exc))
        raise HTTPException(
            status_code=502,
            detail={"error": "AI provider call failed", "meta": {"task": task, "valid_json": False}},
        )

    if not _validate_contract(task, parsed):
        logger.warning("ai task=%s status=failure reason=invalid_shape", task)
        raise HTTPException(
            status_code=422,
            detail={"error": "Model JSON did not match expected schema", "meta": {"task": task, "valid_json": False}},
        )

    logger.info("ai task=%s status=success", task)
    return {
        "result": parsed,
        "meta": {
            "task": task,
            "valid_json": True,
        },
    }
