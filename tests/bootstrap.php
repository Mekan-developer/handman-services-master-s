<?php

require __DIR__.'/../vendor/autoload.php';

/*
 * Переносим тестовое окружение из phpunit.xml в $_SERVER.
 *
 * docker-compose пробрасывает боевой .env в контейнер через env_file, а Laravel
 * читает окружение репозиторием, где ServerConstAdapter идёт раньше putenv().
 * PHPUnit же (даже с force="true") пишет только в putenv() и $_ENV, поэтому без
 * этой синхронизации тесты уходили на реальные Redis и MySQL и протекали
 * состоянием друг в друга. Значения берём из самого phpunit.xml, чтобы не
 * заводить второй список настроек.
 */
$configuration = simplexml_load_file(__DIR__.'/../phpunit.xml');

foreach ($configuration->php->env as $variable) {
    $_SERVER[(string) $variable['name']] = (string) $variable['value'];
}
