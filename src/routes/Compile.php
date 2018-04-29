<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

$app->post('/compile', function (Request $request, Response $response) {
    $fileSystem = new Filesystem();

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['file'];

    if (!isset($uploadedFile)) {
        return $response->withStatus(400)->withJson(array(
            'success' => false,
            'payload' => ['errorCode' => 400, 'errorReason' => 'badRequest'],
            'message' => 'The file was not found.'
        ));
    }

    if (
        $uploadedFile->getSize() > 25000000 ||
        $uploadedFile->getError() === UPLOAD_ERR_INI_SIZE ||
        $uploadedFile->getError() === UPLOAD_ERR_FORM_SIZE
    ) {
        return $response->withStatus(413)->withJson(array(
            'success' => false,
            'payload' => [
                'errorCode' => 413,
                'errorReason' => 'payloadTooLarge'
            ],
            'message' => 'The file is too large.'
        ));
    }

    $uploadedFileExtension = substr(
        $uploadedFile->getClientFilename(),
        strrpos($uploadedFile->getClientFilename(), '.') + 1
    );

    if ($uploadedFileExtension !== 'zip') {
        return $response->withStatus(406)->withJson(array(
            'success' => false,
            'payload' => ['errorCode' => 406, 'errorReason' => 'notAcceptable'],
            'message' => 'The file extention is not `zip`.'
        ));
    }

    $generatedTempPath = generateTempPath(
        strtok($uploadedFile->getClientFilename(), '.')
    );
    $generatedTempPathZip = $generatedTempPath . '.zip';
    $generatedTempPathPhar = $generatedTempPath . '.phar';
    $uploadedFile->moveTo($generatedTempPathZip);

    $uploadedFileZip = new ZipArchive();
    $fileSystem->mkdir($generatedTempPath);
    $uploadedFileZip->open($generatedTempPathZip);
    $uploadedFileZip->extractTo($generatedTempPath);
    $uploadedFileZip->close();

    if (!file_exists($generatedTempPath . DIRECTORY_SEPARATOR . 'plugin.yml')) {
        return $response->withStatus(406)->withJson(array(
            'success' => false,
            'payload' => ['errorCode' => 406, 'errorReason' => 'notAcceptable'],
            'message' => 'The file does not contain a `plugin.yml` in `/`.'
        ));
    }

    if (
        file_exists(
            $generatedTempPath . DIRECTORY_SEPARATOR . 'minecraft-tools.yml'
        )
    ) {
        try {
            $uploadedFileMinecraftToolsManifest = Yaml::parse(
                file_get_contents(
                    $generatedTempPath .
                    DIRECTORY_SEPARATOR .
                    'minecraft-tools.yml'
                )
            );
        } finally {
            if ($uploadedFileMinecraftToolsManifest) {
                if (
                    isset(
                        $uploadedFileMinecraftToolsManifest[
                            'worksWithOnlineConverter'
                        ]
                    )
                ) {
                    if (
                        is_bool(
                            $uploadedFileMinecraftToolsManifest[
                                'worksWithOnlineConverter'
                            ]
                        )
                    ) {
                        if (
                            $uploadedFileMinecraftToolsManifest[
                                'worksWithOnlineConverter'
                            ] ==
                            false
                        ) {
                            return $response->withStatus(400)->withJson(array(
                                'success' => false,
                                'payload' => [
                                    'errorCode' => 400,
                                    'errorReason' => 'badRequest'
                                ],
                                'message' => 'The file could not be compiled due to limitations set by the author.'
                            ));
                        }
                    }
                }
            }
        }
    }
    try {
        $uploadedFileManifest = Yaml::parse(
            file_get_contents(
                $generatedTempPath . DIRECTORY_SEPARATOR . 'plugin.yml'
            )
        );
    } catch (Exception $e) {
        var_dump($e);
        return $response->withStatus(406)->withJson(array(
            'success' => false,
            'payload' => ['errorCode' => 406, 'errorReason' => 'notAcceptable'],
            'message' => 'The file does contain a valid `plugin.yml`.'
        ));
    }

    if (!$uploadedFileManifest['name']) {
        $uploadedFileManifest['name'] = 'Unknown';
    }

    if (!$uploadedFileManifest['version']) {
        $uploadedFileManifest['version'] = '1.0.0';
    }

    $uploadedFilePhar = new Phar($generatedTempPathPhar);
    $uploadedFilePhar->setStub(
        '<?php echo \'This file was compiled using https://minecraft-tools.fun\'; __HALT_COMPILER();'
    );
    $uploadedFilePhar->buildFromDirectory($generatedTempPath);

    try {
        $fileLink = uploadFileToSpaces(
            $generatedTempPathZip,
            $uploadedFileManifest['name'] .
            '_v' .
            $uploadedFileManifest['version'] .
            '_' .
            bin2hex(random_bytes(3)) .
            '.zip'
        );
    } catch (Exception $e) {
        return $response->withStatus(500)->withJson(array(
            'success' => false,
            'payload' => ['errorCode' => 500, 'errorReason' => 'backendError'],
            'message' => 'The file could not be uploaded.'
        ));
    } finally {
        $fileSystem->remove($generatedTempPathPhar);
        $fileSystem->remove($generatedTempPathZip);
        $fileSystem->remove($generatedTempPath);
    }
    return $response->withStatus(201)->withJson(array(
        'success' => true,
        'payload' => ['temporaryFileUrl' => $fileLink],
        'message' => 'The file was successfully converted.'
    ));
});
