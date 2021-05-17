# adminer-tree-view

Adds posibility to display related rows from different tables in one page

# Installation

## Simple installation (you don't use other plugins yet)

If you don't have Adminer or have one but don't use any plugins with it yet, you can use simple instalation. That is basically capy/paste of adminer with prepared settings file for tree-view plugin. Follow these instructions:

 * Download this repository and copy content of folder [demo/](https://github.com/PetroKostyuk/adminer-tree-view/tree/master/demo) to folder on your server.
 * That's it! You can open index.php in your browser and you'll get Adminer with tree-view plugin.
 * (optional) For higher security (especially on production servers), open file `index.php` and delete definition and usage of class `AllowEmptyPasswordPlugin`. This class was used to display usage of custom plugin alongside with tree-view plugin and allows users with no password protection to use Adminer.
 * (optional) You can download most recent version of Adminer and use it instead of version that is used in demo. You'll just need to replace last line in file `index.php` that is including adminer file with include to your version of Adminer.
 * (optional) You can delete file `library2.sql`. It contains demo database used for screenshots, but have no effect on plugin itself.

## Advanced installation (you already use other plugins)

If you already have adminer with some plugins you use and you already have your custom configuration for plugins, youl'll need to follow these instructions:

 * Download this repository and copy content of folder [dist/](https://github.com/PetroKostyuk/adminer-tree-view/tree/master/dist) alongside rest of your plugins.
 * Open your Adminer configuration file (the one that loads all plugins and then starts Adminer itself) and inside of it:
   * Include file `dist/AdminerTreeViewer.php` on same place you include rest of your plugin files.
   * Create instance of class `AdminerTreeViewer(scriptSrc)` and as argument pass path to file `dist/script.js` you copied earlier.
   * Add instance of `AdminerTreeViewer` class you created into array of plugins used as argument by class `AdminerPlugin`.
 * That's it. This should be enough to add this plugin to your Adminer alongside your other plugins.

If some steps were not clear, you can check out folder [demo/](https://github.com/PetroKostyuk/adminer-tree-view/tree/master/demo) to see example of configured plugin or visit section about plugins on official Adminer site https://www.adminer.org/en/plugins/ 

# Usage

This plugin will add new column to your tables with data:

![new columns](images/new-column.png)

After clicking on **Tree** link, modal window will be opened where you can start browsing related data via foreign keys:

![plugin usage](images/usage-sample.png)
