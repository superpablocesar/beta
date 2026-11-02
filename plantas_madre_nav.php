<nav class="navbar expand navbar-dark" aria-label="Nav">
        <div class="container-lg px-3">
            <a class="navbar-brand" href="./">
                <div class="fs-15 text-admin-3 ui-f-400 mb-0"><i class="fa-light fa-chevron-left me-1"></i>Salir de <b>Plantas Madre</b></div>
            </a>
            <?php if (isset($total_pages) && $total_pages > 1): ?>
                <ul class="pagination justify-content-center my-1">
                    <?php
                    $max_visible_pages = 5;
                    $start_page = max(1, $page - floor($max_visible_pages / 2));
                    $end_page = $start_page + $max_visible_pages - 1;

                    if ($end_page > $total_pages) {
                        $end_page = $total_pages;
                        $start_page = max(1, $end_page - $max_visible_pages + 1);
                    }
                    ?>

                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                <small><i class="fa-solid fa-angle-left"></i></small>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                <small><i class="fa-solid fa-angle-right"></i></small>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
            <a href="./plantas_madre_agregar.php<?php echo '?page=' . (isset($_GET['page']) && intval($_GET['page']) > 0 ? intval($_GET['page']) : 1); ?>" class="btn-nav btn btn-dark ms-1 my-2" title="Agregar planta madre">
                <i class="fa-light fa-plus"></i>
            </a>
        </div>
    </nav>
</div>