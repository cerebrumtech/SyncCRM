<?php
function g($v) { return match($v) { 1 => "a", default => "b" }; }
