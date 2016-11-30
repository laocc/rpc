<?php

$yac = new \Yac('time');

print_r(json_decode($yac->get('state1'),true));
print_r(json_decode($yac->get('state2'),true));

$val = [
    'time' => $yac->get('time'),
    'time1' => $yac->get('time1'),
    'time2' => $yac->get('time2'),
];
print_r($val);

echo ($val['time2'] - $val['time1']) * 1000;