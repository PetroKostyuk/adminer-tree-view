<?php

function adminer_object() {
    // required to run any plugin
    include_once "./plugins/plugin.php";
    require_once "./plugins/AdmirerTreeViewer.php";

    $plugins = array(
        // specify enabled plugins here
        new AdmirerTreeViewer(),
    );


    class AdminerCustomization extends AdminerPlugin{
        function login($login, $password) {
            return true;
        }
    }

    return new AdminerCustomization($plugins);
}

// include original Adminer or Adminer Editor
include './adminer-4.7.4.php';
?>