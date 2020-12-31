<?php

function adminer_object() {
    // required to run any plugin
    require_once "./plugin.php";
    require_once "./AdminerTreeViewer/AdminerTreeViewer.php";

    class AdminerCustomization extends AdminerPlugin {
        function login($login, $password) {
            return true;
        }
    }

    return new AdminerCustomization([
        new AdminerTreeViewer("AdminerTreeViewer/script.js")
    ]);
}

// include original Adminer or Adminer Editor
include './adminer-4.7.4.php';
?>