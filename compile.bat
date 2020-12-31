REM # prepare dist folder
@RD /S /Q "dist"
mkdir dist

REM # compile dist folder content
CMD /C npx tsc --outFile dist\script.js src\script.ts
copy src\AdminerTreeViewer.php dist\AdminerTreeViewer.php

REM # copy to demo
RD /S /Q "demo\AdminerTreeViewer"
mkdir "demo\AdminerTreeViewer"
copy dist\AdminerTreeViewer.php demo\AdminerTreeViewer\AdminerTreeViewer.php
copy dist\script.js demo\AdminerTreeViewer\script.js