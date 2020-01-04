<?php

function adminer_object() {
    // required to run any plugin
    require_once "./plugins/plugin.php";
    require_once "./plugins/AdminerTreeViewer.php";

    class AdminerCustomization extends AdminerPlugin{
        function login($login, $password) {
            return true;
        }
    }

    return new AdminerCustomization([
        new AdminerTreeViewer()
    ]);
}

// include original Adminer or Adminer Editor
include './adminer-4.7.4.php';
?>