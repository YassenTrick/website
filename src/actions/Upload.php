<?php
use Aws\S3\Exception\S3Exception as SpacesException;
use Aws\S3\S3Client as SpacesClient;

function uploadFileToSpaces(string $filePath, string $fileName): string
{
    try {
        $spaces = new SpacesClient(array(
            'version' => 'latest',
            'region' => getenv('SPACES_REGION'),
            'endpoint' => getenv('SPACES_ENDPOINT'),
            'credentials' => [
                'key' => getenv('SPACES_KEY'),
                'secret' => getenv('SPACES_SECRET')
            ]
        ));
        $result = $spaces->putObject(array(
            'Bucket' => getenv('SPACES_BUCKET'),
            'Key' => $fileName,
            'Body' => fopen($filePath, 'r'),
            'ACL' => 'public-read'
        ));
    } catch (SpacesException $e) {
        throw new Exception($e->getMessage());
    }
    return $result['ObjectURL'];
}
