const globals = require("globals");
const wordpress = require("@wordpress/eslint-plugin");

module.exports = [
  {
    ignores: [
      "node_modules/**",
      "vendor/**",
      "build/**",
      "dist/**",
      "coverage/**",
      "*.min.js",
      "**/*.min.js",
    ],
  },

  {
    files: ["assets/js/**/*.js", "admin/js/**/*.js", "includes/**/*.js"],

    plugins: {
      "@wordpress": wordpress,
    },

    languageOptions: {
      ecmaVersion: 2021,
      sourceType: "script",
      globals: {
        ...globals.browser,
        ...globals.jquery,

        wp: "readonly",
        ajaxurl: "readonly",
        kerbcycleQrScanner: "readonly",
        KC_OSRM: "readonly",
        Html5Qrcode: "readonly",
        Html5QrcodeScanner: "readonly",
        BarcodeDetector: "readonly",
        L: "readonly",
      },
    },

    rules: {
      "no-undef": "error",
      "no-unused-vars": [
        "warn",
        {
          args: "after-used",
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
        },
      ],
      "no-console": "off",
      "no-alert": "warn",
      "no-debugger": "error",
      "no-dupe-keys": "error",
      "no-duplicate-case": "error",
      "no-unreachable": "error",
      "valid-typeof": "error",
      eqeqeq: ["warn", "smart"],
      curly: ["warn", "multi-line"],
    },
  },
];
