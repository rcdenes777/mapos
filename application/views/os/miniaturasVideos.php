<script>
(function () {
    'use strict';
    var iniciados = new WeakSet();
    function iniciar() {
    document.querySelectorAll('video.os-video-miniatura, .anexo-celula video').forEach(function (video) {
        if (iniciados.has(video)) { return; }
        iniciados.add(video);
        var miniatura = video.classList.contains('os-video-miniatura');
        var capturada = false;
        function capturar() {
            if (capturada || video.readyState < 2 || !video.videoWidth) { return; }
            try {
                var canvas = document.createElement('canvas');
                canvas.width = Math.min(video.videoWidth, 640);
                canvas.height = Math.round(canvas.width * video.videoHeight / video.videoWidth);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                var poster = canvas.toDataURL('image/jpeg', 0.85);
                if (miniatura) {
                    var imagem = document.createElement('img');
                    imagem.src = poster;
                    imagem.alt = 'Abrir vídeo';
                    imagem.style.cssText = video.style.cssText;
                    video.replaceWith(imagem);
                    video.removeAttribute('src');
                    video.load();
                } else {
                    video.poster = poster;
                }
                capturada = true;
            } catch (erro) {
                // A reprodução permanece disponível se a captura não for suportada.
            }
        }
        if (!miniatura && video.controls) {
            document.addEventListener('visibilitychange', function () {
                if (document.hidden || !video.paused) { return; }
                var posicao = video.ended ? 0.1 : video.currentTime;
                video.addEventListener('loadedmetadata', function () {
                    if (Number.isFinite(video.duration)) {
                        video.currentTime = Math.min(posicao || 0.1, Math.max(0, video.duration - 0.01));
                    }
                }, {once: true});
                video.load();
            });
        }
        video.addEventListener('seeked', capturar);
        video.addEventListener('loadeddata', capturar);
        capturar();
    });
    }
    iniciar();
    var anexos = document.getElementById('divAnexos');
    if (anexos) { new MutationObserver(iniciar).observe(anexos, {childList: true, subtree: true}); }
})();
</script>
