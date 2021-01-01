<?php

class AllowEmptyPasswordPlugin {
    function login($login, $password) {
        return true;
    }
}

function adminer_object() {
    // Adminer customization allowing usage of plugins
    require_once "./plugin.php";
    require_once "./AdminerTreeViewer/AdminerTreeViewer.php";

    return new AdminerPlugin([
        new AllowEmptyPasswordPlugin(),
        new AdminerTreeViewer("AdminerTreeViewer/script.js")
    ]);
}

// include original Adminer or Adminer Editor
include './adminer-4.7.4.php';
?>
