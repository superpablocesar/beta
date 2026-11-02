<style>
:root {
    --main-color-1: #64fd2d;
    --main-color-2: #22c1c3;
    --main-color-3: #6c757d;
    --main-color-4: #42f502;
    --main-color-5: #7db1fe;
    --main-color-6: #ffffff;
}

/* preloader */
.ui-loader div:after {background: rgba(108, 117, 125, 0.75);}
.ui-loader-txt {position: absolute; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: rgb(255 255 255 / 75%); font-size: 12px; top: -25px;}
.ui-loader-bg {transition: all 0.25s ease; transform: translateX(0%);}
.ui-loader-bg.sale {
    transform: translateY(-100%); transition: all 0.25s cubic-bezier(0.5, 0, 0, 1); transition-delay: 0.3s; transition-duration: 0.3s
}
.ui-loader-bg {z-index: 1040!important; top: 0px;}
.ui-loader {display: inline-block; position: absolute; width: 80px; height: 80px;}
.ui-loader div {animation: loader 1s cubic-bezier(0.5, 0.25, 0.5, 0.25) infinite; transform-origin: 40px 40px;}
.ui-loader div:after {content: " "; display: block; position: absolute; width: 5px; height: 5px; border-radius: 50%; margin: -4px 0 0 -4px;}
.ui-loader div:nth-child(1) {animation-delay: -0.036s;}
.ui-loader div:nth-child(1):after {top: 63px; left: 63px;}
.ui-loader div:nth-child(2) {animation-delay: -0.072s;}
.ui-loader div:nth-child(2):after {top: 68px; left: 56px;}
.ui-loader div:nth-child(3) {animation-delay: -0.108s;}
.ui-loader div:nth-child(3):after {top: 71px; left: 48px;}
.ui-loader div:nth-child(4) {animation-delay: -0.144s;}
.ui-loader div:nth-child(4):after {top: 72px; left: 40px;}
.ui-loader div:nth-child(5) {animation-delay: -0.18s;}
.ui-loader div:nth-child(5):after {top: 71px; left: 32px;}
.ui-loader div:nth-child(6) {animation-delay: -0.216s;}
.ui-loader div:nth-child(6):after {top: 68px; left: 24px;}
.ui-loader div:nth-child(7) {animation-delay: -0.252s;}
.ui-loader div:nth-child(7):after {top: 63px; left: 17px;}
.ui-loader div:nth-child(8) {animation-delay: -0.288s;}
.ui-loader div:nth-child(8):after {top: 56px; left: 12px;}
@keyframes loader {
  0% {transform: rotate(0deg);}
  100% {transform: rotate(360deg);}
}
.ui-loader-bg {position: fixed; top: 0; left: 0; width: 100%; height: 100%;}
.z-loader {z-index: 1050!important;}
/* preloader */
/* form login */
#loginForm input {font-size: 1rem;}
#loginForm .form-control-lg:focus {outline: none; box-shadow: none; border-color: #dee2e6!important;}
.passeye, .passeye:hover, .passeye:active, .passeye:focus {border-color: #dee2e6!important}
.ui-transition-in-up {transform:translate(0,-10px); transition: all 0.5s ease-in-out;}
/* form login */
/* base minimal */
body {
    font-family: "Google Sans", sans-serif;
    font-optical-sizing: auto;
    font-weight: 400;
    font-style: normal;
    font-variation-settings: "GRAD" 0;
}
html {min-height:100%}
.ui-loader-bg {position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #07010c;}
.z-loader {z-index: 1050!important;}
.dropzone, .dropzone_light {border-radius: 10px; padding: 95px 25px; text-align: center; cursor: pointer; transition: background-color 0.3s ease;}
.dropzone.dark {background: #16181b;}
.dropzone.light {background: #ffffff;}
.dropzone.dark.dragover {background-color: #2c3237;}
.dropzone.light.dragover {background-color: #f5f5f5;}
.dropzone #mensaje {font-size: 14px; margin:5px 0px 6px;line-height:18px;}
.dropzone img {max-width: 96%;margin:0% 2%;height: auto;}
.dropzone.dark #mensaje {color:#eaeaea;}
.no-b-r-t {-webkit-border-top-left-radius: 0px; -webkit-border-top-right-radius: 0px; -moz-border-radius-topleft: 0px; -moz-border-radius-topright: 0px; border-top-left-radius: 0px; border-top-right-radius: 0px;}
.no-b-r-b {-webkit-border-bottom-left-radius: 0px; -webkit-border-bottom-right-radius: 0px; -moz-border-radius-bottomleft: 0px; -moz-border-radius-bottomright: 0px; border-bottom-left-radius: 0px; border-bottom-right-radius: 0px;}
.fs-11 {font-size: 11px;}
.fs-12 {font-size: 12px;}
.fs-13 {font-size: 13px;}
.fs-14 {font-size: 14px;}
.fs-15 {font-size: 15px;}
.fs-20 {font-size: 20px;}
.fs-40 {font-size: 40px;}
.fs-50 {font-size: 50px;}
.fw-900 {font-weight: 900;}
.fs-btn-i {font-size: 23px;}
.t-3 {top:3px}
.t-6 {top:6px}
.mh-100vh {min-height:100vh}
.btn-ampliar {right: 20px; height: 55px; width: 55px; top: calc(50% - 30px); padding: 13px;}
.btn-volver {right: 16px; height: 44px; width: 47px; top: calc(50% + 30px); padding-bottom: 2px;}
.dd-handle {cursor: move;}
.dd {position: relative; display: block; margin: 0; padding: 0; max-width: 600px; list-style: none; line-height: 20px;}
.dd-list {display: block; position: relative; margin: 0; padding: 0; list-style: none;}
.dd-list .dd-list {padding-left: 30px;}
.dd-item, .dd-empty, .dd-placeholder {display: block; position: relative; margin: 0; padding: 0; min-height: 20px; line-height: 20px;}
.dd-handle:hover {color: #2ea8e5; background: #fff;}
.dd-item > button {position: relative; cursor: pointer; float: left; width: 25px; height: 20px; margin: 5px 0; padding: 0; text-indent: 100%; white-space: nowrap; overflow: hidden; border: 0; background: transparent; font-size: 12px; line-height: 1; text-align: center; font-weight: bold; }
.dd-item > button:before {display: block; position: absolute; width: 100%; text-align: center; text-indent: 0;}
.dd-item > button.dd-expand:before {content: '+';}
.dd-item > button.dd-collapse:before {content: '-';}
.dd-expand {display: none;}
.dd-collapsed .dd-list, .dd-collapsed .dd-collapse {display: none;}
.dd-collapsed .dd-expand {display: block;}
.dd-empty, .dd-placeholder {margin: 5px 0; padding: 0; min-height: 40px; background: #f2fbff; border: 1px dashed #b6bcbf; box-sizing: border-box; -moz-box-sizing: border-box;}
.dd-empty {border: 1px dashed #bbb; min-height: 100px; background-color: #e5e5e5; background-size: 60px 60px; background-position: 0 0, 30px 30px;}
.dd-dragel {position: absolute; pointer-events: none; z-index: 9999;}
.dd-dragel > .dd-item .dd-handle {margin-top: 0;}
.dd-dragel .dd-handle {opacity: 0.95;}
.dd-nochildren .dd-placeholder {display: none;}
.clamp-1 {-webkit-line-clamp: 1; line-clamp: 1;}
.clamp-2 {-webkit-line-clamp: 2; line-clamp: 2; min-height: 52px;}
.clamp-3 {-webkit-line-clamp: 3; line-clamp: 3;}
.clamp-7 {-webkit-line-clamp: 7; line-clamp: 7; min-height: 120px;}
.clamp-1, .clamp-2, .clamp-3 {display: -webkit-box!important; -webkit-box-orient: vertical; overflow: hidden;}
.tag-noticias, .tag-novedades {top: 15px; border: 1px solid rgba(255,255,255,0.75); padding: 5px 12px; font-size: 12px; left: -2px; -webkit-border-top-right-radius: 5px; -webkit-border-bottom-right-radius: 5px; -moz-border-radius-topright: 5px; -moz-border-radius-bottomright: 5px; border-top-right-radius: 5px; border-bottom-right-radius: 5px;}
.tag-novedades {font-size: 13px;}
.form-switch.form-switch-md {margin-bottom: 1rem;}
.form-switch.form-switch-md .form-check-input {height: 1.5rem; width: calc(2rem + 0.75rem); border-radius: 3rem; margin-left: 0;}
.form-switch.form-switch-lg {margin-bottom: 1.5rem;}
.form-switch.form-switch-lg .form-check-input {height: 2rem; width: calc(3rem + 0.75rem); border-radius: 4rem; margin-left: 0;}
.form-switch {padding-left: 0!important;}
.form-switch .form-check-label {display: block;}
/* base minimal */
/* font-weight */
.ui-f-400{font-weight: 400;}
.ui-f-500{font-weight: 500;}
.ui-f-600{font-weight: 600;}
.ui-f-700{font-weight: 700;}
.ui-f-800{font-weight: 800;}
.ui-f-900{font-weight: 900;}
/* font-weight */
/* colorimetria */
.tag-noticias, .tag-novedades {background: var(--main-color-1);}
.passeye:hover i, .text-admin {color: var(--main-color-1);}
.text-admin-2 {color: var(--main-color-2);}
.text-admin-3 {color: var(--main-color-3);}
.text-admin-4 {color: var(--main-color-4);}
.text-admin-5 {color: var(--main-color-5);}
.text-admin-6 {color: var(--main-color-6);}

a.page-link {color: var(--main-color-3);}
a.page-link:hover {color: var(--main-color-1);}
.active>.page-link, .page-link.active {background-color: var(--main-color-3); border-color: var(--main-color-3);}
/* colorimetria */
/* menu card */
    .ui-bg-2:has(.card:hover) {}
    .ui-card {color:#787878; text-decoration:none!important;  box-shadow: 0 0 15px 3px rgb(6 6 6 / 10%)}
    .ui-card:hover {background: #22C1C3; background: linear-gradient(-15deg, rgba(34, 193, 195, 1) 0%, rgba(100, 253, 45, 1) 100%); transition: all 0.5s ease; box-shadow: 0px -5px 10px 1px rgb(120 252 73 / 50%), 0 5px 9px 2px rgb(0 144 144 / 50%), inset 0 0 11px 1px rgb(255 255 255 / 90%); color:#ffffff; text-shadow: 0 4px 4px rgb(0 0 0 / 20%), 0 1px 2px rgb(0 0 0 / 85%)}
    .ui-card-list {color:#787878; text-decoration:none!important;  box-shadow: 0 0 15px 3px rgb(6 6 6 / 10%)}
    .ui-card-list:hover {background: #22C1C3; background: linear-gradient(155deg, rgba(30, 172, 173, 1) 0%, rgba(100, 253, 45, 1) 100%); transition: all 0.25s ease; box-shadow: 0px -5px 10px 1px rgb(120 252 73 / 50%), 0 5px 9px 2px rgb(0 144 144 / 50%), inset 0 0 11px 1px rgb(255 255 255 / 90%); color:#ffffff; text-shadow: 0 4px 4px rgb(0 0 0 / 20%), 0 1px 2px rgb(0 0 0 / 85%)}
    .ui-text {color: var(--main-color-3);}
    .ui-color {color: var(--main-color-1); text-shadow: -1px -1px 2px #FFFFFF}
    .ui-card {cursor: pointer; transition: all 0.5s ease; overflow: hidden}
    .ui-card .card-body i.ui-card-i-fnd {position: absolute; font-size: 7.5rem; right: -4rem; opacity: 0; transition: all 0.15s ease;}
    .ui-card:hover .card-body i.ui-card-i-fnd {position: absolute; font-size: 7.5rem; right: 1.5rem; opacity: 1; transition: all 0.15s ease; transition-delay: 0.25s}
    .ui-card .card-body div i {font-size: 4rem}
    .ui-card:hover .ui-text {color:#ffffff!important;}
    .ui-card:hover i.ui-text.ui-card-i-fnd {color: var(--main-color-1)!important; text-shadow: none!important}

    .ui-text:not(i) {display:block; transform: translateX(50%); transition: all 0.25s ease}
    .ui-card .card-body div span i {display:block; transform: translateX(calc(50% - 2.5rem)); transition: all 0.25s ease;}
    .ui-card .card-body div span i:before {width: 5rem; text-align: center;}
    .ui-card .card-body div span i:after {width: 5rem!important; text-align: center; display: inline-block;}

    .ui-text small, .ui-text span {display:inline-block; transform: translateX(-50%); transition: all 0.25s ease}
    .ui-card:hover .ui-text {display:block; transform: translateX(0%)}
    .ui-card:hover .ui-text small, .ui-card:hover .ui-text span {display:inline-block; transform: translateX(0%)}

    /* menu card */
.btn-admin {
    --bs-btn-color: #fff;
    --bs-btn-bg: var(--main-color-2);
    --bs-btn-border-color: var(--main-color-2);
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: var(--main-color-1);
    --bs-btn-hover-border-color: var(--main-color-1);
    --bs-btn-focus-shadow-rgb: 66,70,73;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: var(--main-color-1);
    --bs-btn-active-border-color: var(--main-color-1);
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #fff;
    --bs-btn-disabled-bg: var(--main-color-2);
    --bs-btn-disabled-border-color: var(--main-color-2);
}
.btn-outline-admin {
    --bs-btn-color: var(--main-color-3);
    --bs-btn-border-color: var(--main-color-3);
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: var(--main-color-4);
    --bs-btn-hover-border-color: var(--main-color-3);
    --bs-btn-focus-shadow-rgb: 108,117,125;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: var(--main-color-4);
    --bs-btn-active-border-color: var(--main-color-3);
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: var(--main-color-4);
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: var(--main-color-3);
    --bs-gradient: none;
}
.btn-outline-admin-alt {
    --bs-btn-color: var(--main-color-3);
    --bs-btn-border-color: var(--main-color-3);
    --bs-btn-hover-color: var(--main-color-2);
    --bs-btn-hover-bg: #fff;
    --bs-btn-hover-border-color: var(--main-color-2);
    --bs-btn-focus-shadow-rgb: 108,117,125;
    --bs-btn-active-color: var(--main-color-2);
    --bs-btn-active-bg: #fff;
    --bs-btn-active-border-color: var(--main-color-2);
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: var(--main-color-4);
    --bs-btn-disabled-bg: transparent;
    --bs-btn-disabled-border-color: var(--main-color-3);
    --bs-gradient: none;
}
.form-check-input:checked {
    background-color: var(--main-color-2);
    border-color: var(--main-color-2);
}
.bg-admin {background: var(--main-color-2);}
.dia-admin {width: 20px !important; display: inline-block; text-align: center; font-size: 12px; font-weight: 700;}
/* nav pagination */
@media (max-width:575px) {
    .cvs-text {font-size:12px}
    nav.subnavbar .admin-list span {display: none}
    nav.subnavbar .admin-list i.admin-list-i {font-size: calc(100% + 10px)}
    nav.subnavbar a.page-link {font-size: 14px; padding: 0.625rem 0.75rem;}
}
@media (min-width:576px) {
    nav.subnavbar a.page-link {font-size: 14px; padding: 0.625rem 0.75rem;}
}
.aspect-1 {aspect-ratio: 1}
.btn-sugerencia-ia {position: absolute; bottom: 15px; right: 25px; opacity: 0.75;}
.btn-sugerencia-ia:hover, .btn-sugerencia-ia:active, .btn-sugerencia-ia:focus {opacity: 1;}
textarea#texto, textarea#descripcion {padding: 1rem!important}
/* nav pagination */
.admin-i-no {font-size: calc(5rem + 4.5vw)}
i.admin-i-no.opacity-25 {animation: admin-i-change 10s infinite;}

@keyframes admin-i-change {
  0% { color: #282129; }
  50% { color: #923b9f; }
  100% { color: #282129; }
}

.admin-i-no-light {font-size: calc(5rem + 4.5vw)}
i.admin-i-no-light.opacity-25 {animation: admin-i-change-light 10s infinite;}

@keyframes admin-i-change-light {
  0% { color: #caced0; }
  50% { color: #6c757d; }
  100% { color: #caced0; }
}

.admin-i-check {margin-left: calc(3rem + 4.5vw); top: 0;}
.admin-color-check {color: #11ab00!important;}

/** offcanvas body **/
body:has(.offcanvas.show) {
  overflow: hidden !important;
}
.offcanvas {min-width:400px; max-width:444px; width: 100%!important; overscroll-behavior-y: contain;}
.offcanvas-header.ui-light {background: #f2f2f3;}
.ui-bg-1, .offcanvas-body.ui-light {
background: #22C1C3;
background: linear-gradient(0deg, rgba(34, 193, 195, 1) 0%, rgba(100, 253, 45, 1) 100%);
}
.ui-bg-2, .ui-loader-bg {
background: #ededed;
background: linear-gradient(0deg, #ededed 0%, #fafafa 100%);
}
.modal-body, .modal-header, .modal-footer {padding: 1.625rem}

/* notificaciones */
.alertify-notifier .ajs-message{border-radius: 0.625rem!important; color: #FFFFFF;}
.alertify-notifier .ajs-message.ajs-success {background: rgba(32, 169, 64, 1);}

/* acordion */
.accordion-flush {border-radius:10px}
.accordion-flush>.accordion-item:first-child, .accordion-flush>.accordion-item:first-child h2 .accordion-button, .accordion-flush>.accordion-item:first-child h2 .accordion-button.collapsed {
    -webkit-border-top-left-radius: 10px;
    -webkit-border-top-right-radius: 10px;
    -moz-border-radius-topleft: 10px;
    -moz-border-radius-topright: 10px;
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}
.accordion-flush>.accordion-item:last-child,  .accordion-flush>.accordion-item:last-child h2 .accordion-button, .accordion-flush>.accordion-item:last-child h2 .accordion-button.collapsed {
    -webkit-border-bottom-left-radius: 10px;
    -webkit-border-bottom-right-radius: 10px;
    -moz-border-radius-bottomleft: 10px;
    -moz-border-radius-bottomright: 10px;
    border-bottom-left-radius: 10px;
    border-bottom-right-radius: 10px;
}
.bg-alert {background: #efe7f7}
.bg-home-1{
    background: rgba(255, 255, 255, 0.25);
    background: linear-gradient(0deg, rgba(255, 255, 255, 0.35) 0%, rgba(250, 250, 250, 0.25) 30%, rgba(234, 234, 234, 0.25) 60%, rgba(234, 234, 234, 0.35) 100%);}

/* select 2 iconos*/
.select2-container {width: 100%!important; z-index: 1060!important;}
.select2-container--default .select2-selection--single {
    border: 1px solid #dee2e6!important;
    border-radius: 5px!important;
    padding: 5px 0;
}
.select2-container .select2-selection--single {height:38px!important}
.select2-container [type=search] {outline-offset:0px!important}
.select2-container--default .select2-results__option--highlighted.select2-results__option--selectable {background-color: #640fb1!important}
/* select 2 iconos*/
.btn-nav {padding: 0.5rem 0.875rem!important; font-size: 1.125rem!important}
.logout-tit { color: #7111c9}
.logout-msg { color: #640fb1}

#cropper-wrap {
        width: 100%;
        position: relative;
    }
/* Mantiene el contenedor cuadrado 1:1 */
#cropper-viewer {
    width: 100%;
    aspect-ratio: 1 / 1;
}
.img-listado {width: 85px; height: 85px; margin-right: 1rem}
.btn-delete-busquedas {position: absolute; right: 50px;}

/** POSTA CITY */
.numerodeclics {letter-spacing: -0.125rem; line-height: 1; margin-bottom: 0.188rem}
/** POSTA CITY */
.opacity-75 {opacity: 0.75;}
.chart-container { 
    background: #ffffff; 
    padding: 25px; 
    border-radius: 12px; 
    width: 100%!important;
    min-height: 350px;
    box-shadow: 0 4px 20px 0 rgba(0,0,0,.05);
    border: none;
}

/* Estilos Tooltip */
.apexcharts-tooltip { color: #000 !important; }
.tooltip-custom-box { padding: 10px; line-height: 1.4; }
.tooltip-title { font-weight: bold; margin-bottom: 5px; border-bottom: 1px solid #eee; padding-bottom: 3px; }
.tooltip-item { font-size: 11px; color: #444; }
.password-container {
    position: relative;
}

.toggle-password {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}
.btn-dark {
    --bs-btn-color: #fff;
    --bs-btn-bg: #072a2a;
    --bs-btn-border-color: #072a2a;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: #0f5656;
    --bs-btn-hover-border-color: #0f5656;
    --bs-btn-focus-shadow-rgb: 66, 70, 73;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: #4d5154;
    --bs-btn-active-border-color: #373b3e;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #fff;
    --bs-btn-disabled-bg: #212529;
    --bs-btn-disabled-border-color: #212529;
}
.t--t {top: -4px}
</style>