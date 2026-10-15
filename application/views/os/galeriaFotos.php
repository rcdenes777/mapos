<style>
.os-fotos-grade { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; max-width: 704px; margin-top: 12px; }
.os-fotos-grade button { min-width: 0; padding: 4px; border: 1px solid #ddd; border-radius: 4px; background: #fff; cursor: zoom-in; }
.table-condensed td .os-fotos-grade img { display: block; width: 100% !important; height: 180px !important; max-width: 100%; margin: 0; object-fit: contain; object-position: center; }
.os-fotos-ampliada { position: fixed; inset: 0; z-index: 11000; background: rgba(0,0,0,.9); display: flex; align-items: center; justify-content: center; }
.os-fotos-ampliada[hidden] { display: none; }
.os-fotos-ampliada img { width: auto !important; height: auto !important; max-width: 94vw; max-height: 90vh; object-fit: contain; }
.os-fotos-fechar { position: absolute; top: 12px; right: 16px; font-size: 30px; background: #fff; color: #222; border: 0; border-radius: 4px; padding: 8px 12px; cursor: pointer; }
@media (max-width: 480px) { .table-condensed td .os-fotos-grade img { height: 120px !important; } }
</style>
<div class="os-fotos-ampliada" hidden role="dialog" aria-modal="true" aria-label="Foto ampliada">
    <button type="button" class="os-fotos-fechar" aria-label="Fechar foto ampliada">&times;</button>
    <img alt="Foto ampliada da ordem de serviço">
</div>
<script>
(function () {
    'use strict';
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
