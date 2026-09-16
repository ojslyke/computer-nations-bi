<?php
// Expeditions and Transfers share the same underlying table and lifecycle —
// this file just points at the shared, type-aware detail page so there's
// only one implementation to keep correct.
require __DIR__ . '/../transfers/view.php';
