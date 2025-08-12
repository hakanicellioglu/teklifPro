<?php
return [
    'guillotine' => [
        [
            'match' => function(array $row): bool {
                return ($row['system_type'] ?? '') === 'Guillotine';
            },
            'products' => [
                [
                    'name' => 'G_FRAME',
                    'qty' => function(array $r) {
                        $width = (float)($r['width'] ?? 0);
                        $height = (float)($r['height'] ?? 0);
                        $qty = (int)($r['quantity'] ?? 0);
                        return ($width / 1000) * ($height / 1000) * $qty; // m² based
                    }
                ],
                [
                    'name' => 'G_MOTOR',
                    'qty' => function(array $r) {
                        return ($r['motor_system'] ?? '') === 'motorlu' ? (int)($r['quantity'] ?? 0) : 0;
                    }
                ],
                [
                    'name' => 'G_REMOTE',
                    'qty' => function(array $r) {
                        return (int)($r['remote_quantity'] ?? 0);
                    }
                ],
                [
                    'name' => 'G_GLASS_CLR',
                    'qty' => function(array $r) {
                        $width = (float)($r['width'] ?? 0);
                        $height = (float)($r['height'] ?? 0);
                        $qty = (int)($r['quantity'] ?? 0);
                        return ($r['glass_type'] ?? '') === 'clear' ? ($width / 1000) * ($height / 1000) * $qty : 0;
                    }
                ],
            ],
        ],
    ],
    'sliding' => [
        [
            'match' => function(array $row): bool {
                return ($row['system_type'] ?? '') === 'Sliding';
            },
            'products' => [
                [
                    'name' => 'S_FRAME',
                    'qty' => function(array $r) {
                        $width = (float)($r['width'] ?? 0);
                        $qty = (int)($r['quantity'] ?? 0);
                        return ($width / 1000) * $qty;
                    }
                ],
            ],
        ],
    ],
];
