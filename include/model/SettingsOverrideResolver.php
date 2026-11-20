<?php

interface SettingsOverrideResolver
{
    public function resolve(string $name): ?string;
}
