<?php
function f($a) { return $a ?? throw new Exception("x"); }
