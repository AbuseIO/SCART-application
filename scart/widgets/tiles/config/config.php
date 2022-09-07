<?php

return
    [
        'displayColumns' =>
        [
            // 1920 -> 3=120/32, 4=180/46, 6=260/68
            '1920' => [
                '2' => [
                    'colspan' => 6,
                    'boxheightsize' => 550,
                    'imgsize' => 530,
                    'txtsize' => 118,
                ],
                '3' => [
                    'colspan' => 4,
                    'boxheightsize' => 550,
                    'imgsize' => 480,
                    'txtsize' => 76,
                ],
                '4' => [
                    'colspan' => 3,
                    'boxheightsize' => 450,
                    'imgsize' => 380,
                    'txtsize' => 55,
                ],
            ],
            // 1620 -> 3=110/26, 4=160/39, 6=220/60
            '1620' => [
                '2' => [
                    'colspan' => 6,
                    'boxheightsize' => 500,
                    'imgsize' => 480,
                    'txtsize' => 94,
                ],
                '3' => [
                    'colspan' => 4,
                    'boxheightsize' => 400,
                    'imgsize' => 380,
                    'txtsize' => 60,
                ],
                '4' => [
                    'colspan' => 3,
                    'boxheightsize' => 400,
                    'imgsize' => 350,
                    'txtsize' => 45,
                ],
            ],
            // 1280 -> 3=80/16, 4=120/26, 6=190/46
            '1020' => [
                '2' => [
                    'colspan' => 6,
                    'boxheightsize' => 500,
                    'imgsize' => 480,
                    'txtsize' => 75,
                ],
                '3' => [
                    'colspan' => 4,
                    'boxheightsize' => 400,
                    'imgsize' => 380,
                    'txtsize' => 45,
                ],
                '4' => [
                    'colspan' => 3,
                    'boxheightsize' => 400,
                    'imgsize' => 300,
                    'txtsize' => 32,
                ],
            ],
            // small fallback -> one column
            '0' => [
                '2' => [
                    'colspan' => 12,
                    'boxheightsize' => 400,
                    'imgsize' => 100,
                    'txtsize' => 10,
                ],
                '3' => [
                    'colspan' => 12,
                    'boxheightsize' => 400,
                    'imgsize' => 100,
                    'txtsize' => 10,
                ],
                '4' => [
                    'colspan' => 6,
                    'boxheightsize' => 400,
                    'imgsize' => 100,
                    'txtsize' => 10,
                ],
            ]
        ]


];
