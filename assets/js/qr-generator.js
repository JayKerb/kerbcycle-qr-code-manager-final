(function($){
  function toggleMode() {
    const mode = $('#kc-gen-mode').val();
    $('.kc-if-single').toggle(mode === 'single');
    $('.kc-if-random').toggle(mode === 'random');
    $('.kc-if-seq').toggle(mode === 'sequential');
  }

  $('#kc-gen-mode').on('change', toggleMode);
  toggleMode();

  $('#kc-generate-btn').on('click', function(){
    const mode = $('#kc-gen-mode').val();
    const payload = {
      action: 'kerbcycle_generate_qr',
      nonce: KerbcycleQRGen.nonce,
      mode,
      genType: mode
    };

    if (mode === 'single') {
      payload.code = $('#kc-code').val().trim();
    } else if (mode === 'random') {
      payload.count  = parseInt($('#kc-count').val(), 10) || 1;
      payload.prefix = $('#kc-prefix').val().trim();
      payload.length = parseInt($('#kc-length').val(), 10) || 8;
    } else if (mode === 'sequential') {
      payload.seqPrefix = $('#kc-seq-prefix').val().trim();
      payload.seqStart  = parseInt($('#kc-seq-start').val(), 10) || 1;
      payload.seqCount  = parseInt($('#kc-seq-count').val(), 10) || 1;
      payload.seqPad    = parseInt($('#kc-seq-pad').val(), 10) || 4;
    }

    const $btn = $(this).prop('disabled', true).text('Generating...');
    $.post(KerbcycleQRGen.ajaxUrl, payload).done(function(resp){
      $btn.prop('disabled', false).text('Generate & Save');
      if (!resp || !resp.success) {
        alert((resp && resp.data && resp.data.message) || 'Failed.');
        return;
      }
      const saved   = resp.data.saved || [];
      const skipped = resp.data.skipped || [];
      const $out = $('#kc-generate-result').empty();

      saved.forEach(function(code){
        const card = $('<div class="kc-card kc-card-grid"></div>');
        const qr   = $('<div class="kc-qr"></div>').appendTo(card);
        $('<div class="kc-code"></div>').text(code).appendTo(card);
        $out.append(card);
        new QRCode(qr[0], { text: code, width: 128, height: 128, correctLevel: QRCode.CorrectLevel.M });
      });

      if (skipped.length) {
        alert('Skipped existing duplicates:\n' + skipped.join(', '));
      }
    }).fail(function(){
      $btn.prop('disabled', false).text('Generate & Save');
      alert('Request failed.');
    });
  });
})(jQuery);
