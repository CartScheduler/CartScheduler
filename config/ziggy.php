<?php
return [
    'except' => ['admin.*','_debugbar.*','debugbar.*', '_ignition.*', 'ignition.*'],
    'groups' => [
        'admin' => ['admin.*', 'users.*'],
    ],
];
