<?php
function t(array $a, ?string $b = null): string { return implode(",", $a) . (string) $b; }
