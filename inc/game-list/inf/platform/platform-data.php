<?php


function get_platform_order() {
    return array(
		'Switch 2'      => 0,
        'Switch'      => 1,
		'WiiU'      => 2,
		'Wii'      => 3,
		 'NGC'     => 4,
		  'NES'    => 5,
		'3DS'      => 10,
		'PS5'         => 19,
        'PS4'         => 20,
         'PS3'        => 30,
		'Vita'         => 31,
		'Series X|S'        => 35,
        'XONE'        => 40,
         'X360' => 50,
        'PC'          => 60,
        'Mac'         => 70,
        'Linux'       => 80,
		'browser'         => 85,
        'iOS'         => 90,
        'Android'     => 100,
		'PSVR2'       => 31,
         'PSVR'      => 32,
        'Steam VR'    => 81,
		'Meta Quest 2'    => 110,
		'Stadia'    => 120
    );
}

function get_platform_colors() {
    return array(
		'Switch 2'      => '#ff0000',
        'Switch'      => '#ff0000',
		'NES'      => '#ff0000',
		'NGC'      => '#ff0000',
		'Wii'      => '#ff0000',
		'WiiU'      => '#ff0000',
		'3DS'      => '#ff0000',
		'PS3'         => '#003087',
        'PS4'         => '#003087',
        'PS5'         => '#003087',
		'Vita'         => '#003087',
		'X360'        => '#52b043',
        'XONE'        => '#52b043',
        'Series X|S'  => '#52b043',
        'PC'          => '#ffa500',
        'Mac'         => '#ffa500',
        'Linux'       => '#ffa500',
		'browser'     => '#ffa500',
        'iOS'         => '#8a2be2',
        'Android'     => '#8a2be2',
		'PSVR'       =>  '#003087',
        'PSVR2'       => '#003087',
        'Steam VR'    => '#ffa500',
		'Meta Quest 2'    => '#0099ff',
		'Stadia'    => '#0099ff'
    );
}