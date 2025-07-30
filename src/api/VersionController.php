<?php
class VersionController
{
    public function handle(): void
    {
        ErrorResponseHelper::json([
            'success' => true,
            'data'    => [
                'version' => VERSION_OF_PROGRAM
            ]
        ]);
    }
}
