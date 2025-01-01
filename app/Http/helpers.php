<?php


function assetLink(string $type, string $key)
{
    return asset(\Config::get("link.{$type}.{$key}"));
}
