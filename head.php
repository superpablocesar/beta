<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Buho">
    <meta name="theme-color" content="#000000">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>FLOWHER</title>
    
    <link href="https://buho.pm/admin/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://buho.pm/admin/css/alertify.css" rel="stylesheet">
    <link rel="preload" href="./iconos/css/all.min.css?v=1.1" as="style" onload="this.rel='stylesheet'">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:ital,opsz,wght@0,17..18,400..700;1,17..18,400..700&display=swap" rel="stylesheet">

    <script src="https://buho.pm/admin/js/alertify.js"></script>
    <script src="./validacion_alertify.js" defer></script>
    <?php
    require_once __DIR__ . '/alertify_error.php';
    mostrar_error_alertify_flash_en_formulario();
    ?>
    <link rel="icon" type="image/png" href="https://buho.pm/img/favicon.png">
    <link rel="shortcut icon" type="image/png" href="https://buho.pm/img/favicon.png">

    <?php include './estilos.css.php';?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!--<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />-->
    <!--<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">-->

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css"/>
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css"/>
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js"></script>
</head>
