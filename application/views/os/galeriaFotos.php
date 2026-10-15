<style>
.os-fotos-grade { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; max-width: 704px; margin-top: 12px; }
.os-fotos-grade button { min-width: 0; padding: 4px; border: 1px solid #ddd; border-radius: 4px; background: #fff; cursor: zoom-in; }
.table-condensed td .os-fotos-grade img { display: block; width: 100% !important; height: 180px !important; max-width: 100%; margin: 0; object-fit: contain; object-position: center; }
.os-fotos-ampliada { position: fixed; inset: 0; z-index: 11000; background: rgba(0,0,0,.9); display: flex; align-items: center; justify-content: center; }
.os-fotos-ampliada[hidden] { display: none; }
.os-fotos-ampliada img { width: auto !important; height: auto !important; max-width: 94vw; max-height: 90vh; object-fit: contain; }
.os-fotos-fechar { position: absolute; top: 12px; right: 16px; font-size: 30px; background: #fff; color: #222; border: 0; border-radius: 4px; padding: 8px 12px; cursor: pointer; }
@media (max-width: 480px) { .table-condensed td .os-fotos-grade img { height: 120px !important; } }
@media screen and (max-width: 767px) {
    #printOs { padding: 10px; min-width: 0; }
    #printOs .invoice-content { padding: 0; }
    #printOs .invoice-head { overflow: visible; }
    #printOs table { max-width: 100%; }
    #printOs .os-dados, #printOs .os-dados tbody, #printOs .os-dados tr, #printOs .os-dados td {
        display: block; width: auto !important; box-sizing: border-box;
    }
    #printOs .os-dados td { overflow-wrap: anywhere; }
    #printOs .os-dados ul { margin-left: 0; }
    #printOs .os-tabela-rolagem { max-width: 100%; overflow-x: auto; }
    #printOs .os-fotos-grade { width: 100%; max-width: 100%; }
    #printOs .anexo-celula { min-width: 0; }
    #printOs video { max-width: 100%; }
    #printOs .os-dados img:not(.os-fotos-grade img) { max-width: 100%; }
}
</style>
<div class="os-fotos-ampliada" hidden role="dialog" aria-modal="true" aria-label="Foto ampliada">
    <button type="button" class="os-fotos-fechar" aria-label="Fechar foto ampliada">&times;</button>
    <img alt="Foto ampliada da ordem de serviço">
</div>
<script>
(function () {
    'use strict';
    document.querySelectorAll('#printOs table').forEach(function (tabela) {
        if (!tabela.querySelector('th')) {
            tabela.classList.add('os-dados');
        } else {
            var rolagem = document.createElement('div');
            rolagem.className = 'os-tabela-rolagem';
            tabela.parentNode.insertBefore(rolagem, tabela);
            rolagem.appendChild(tabela);
        }
    });
    var modal = document.querySelector('.os-fotos-ampliada');
    var ampliada = modal.querySelector('img');
    var fechar = modal.querySelector('button');
    var origem;
    var overflow;
    function encerrar() {
        if (modal.hidden) { return; }
        modal.hidden = true;
        ampliada.removeAttribute('src');
        document.body.style.overflow = overflow;
        if (origem) { origem.focus(); }
    }
    document.querySelectorAll('.os-fotos-campo').forEach(function (campo) {
        var fotos = Array.from(campo.querySelectorAll('img'));
        if (!fotos.length) { return; }
        var grade = document.createElement('div');
        grade.className = 'os-fotos-grade';
        fotos.forEach(function (foto, indice) {
            var botao = document.createElement('button');
            botao.type = 'button';
            botao.setAttribute('aria-label', 'Ampliar foto ' + (indice + 1));
            botao.appendChild(foto);
            grade.appendChild(botao);
            botao.addEventListener('click', function (evento) {
                evento.preventDefault();
                evento.stopPropagation();
                origem = botao;
                overflow = document.body.style.overflow;
                ampliada.src = foto.src;
                modal.hidden = false;
                document.body.style.overflow = 'hidden';
                fechar.focus();
            });
        });
        campo.querySelectorAll('p').forEach(function (paragrafo) {
            if (!paragrafo.textContent.trim() && !paragrafo.querySelector('img,video,iframe')) { paragrafo.remove(); }
        });
        campo.appendChild(grade);
    });
    fechar.addEventListener('click', encerrar);
    document.addEventListener('keydown', function (evento) {
        if (modal.hidden) { return; }
        if (evento.key === 'Escape') { encerrar(); }
        if (evento.key === 'Tab') { evento.preventDefault(); fechar.focus(); }
    });
})();
</script>

<script>
(function () {
    'use strict';
    document.querySelectorAll('video').forEach(function (video) {
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
})();
</script>
