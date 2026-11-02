<?php
    namespace Aowow\Template;

    use \Aowow\Lang;

    /** @var PageTemplate $this */

// aowow - custom: [0 => label, areaId => name, ..] - zones holding spawns without a drawable point, when there is no map at all
if ($zones = array_slice($this->foundInZones, 1, preserve_keys: true)):
    echo '                <div>'.$this->foundInZones[0].' ';
    echo Lang::concat($zones, callback: fn($name, $areaId) => '<a href="?zone='.$areaId.'">'.$name.'</a>');
    echo '.</div>'.PHP_EOL;
endif;
