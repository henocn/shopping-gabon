<?php
exec("cd /home/u772108144/domains/zafrica.shop/public_html && git pull 2>&1", $output);
print_r($output);
?>