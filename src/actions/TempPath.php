<?php
function generateTempPath(string $tempName): string
{
    return (
        sys_get_temp_dir() .
        DIRECTORY_SEPARATOR .
        $tempName .
        '_' .
        bin2hex(random_bytes(5))
    );
}
