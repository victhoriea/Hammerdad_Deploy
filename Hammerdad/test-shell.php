<?php
echo "<pre>";
echo "Python location:\n";
echo shell_exec('where python 2>&1');
echo "\n\nPython version:\n";
echo shell_exec('"C:\Python314\python.exe" --version 2>&1');
echo "\n\nSite-packages path:\n";
echo shell_exec('"C:\Python314\python.exe" -c "import sys; print(sys.path)" 2>&1');
echo "\n\nWhoami (which user Apache runs as):\n";
echo shell_exec('whoami 2>&1');

echo shell_exec('"C:\Python314\python.exe" -c "import joblib; print(joblib.__file__)" 2>&1');
echo "</pre>";