<?php

require __DIR__.'/vendor/autoload.php';

use Akeneo\Pim\ApiClient\AkeneoPimClientBuilder;
use Akeneo\Pim\ApiClient\Exception\NotFoundHttpException;
use Akeneo\Pim\ApiClient\Exception\UploadAssetReferenceFileErrorException;
use League\Csv\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Dotenv\Dotenv;

(new SingleCommandApplication())
    ->setName('Importing images')
    ->setVersion('1.0.0')
    ->addArgument('max', InputArgument::OPTIONAL)
    ->setCode(function (InputInterface $input, OutputInterface $output): int {
        // output arguments and options
        $output->writeln('Syncing Images');

        $dotenv = new Dotenv();
        $dotenv->load(__DIR__.'/.env');

        $clientBuilder = new AkeneoPimClientBuilder($_ENV['AKENEO_NEW_URL']);
        $akeneoClient = $clientBuilder->buildAuthenticatedByPassword(
            $_ENV['AKENEO_NEW_CLIENTID'],
            $_ENV['AKENEO_NEW_SECRET'],
            $_ENV['AKENEO_NEW_USERNAME'],
            $_ENV['AKENEO_NEW_PASSWORD']
        );

        $csv = Reader::createFromPath('sample.csv', 'r');
        $csv->setHeaderOffset(0);

        $progressBar = new ProgressBar($output, $csv->count());
        $progressBar->setFormat(ProgressBar::FORMAT_VERY_VERBOSE);
        $progressBar->start();

        foreach ( $csv->getRecords() as $record) {

            $assetCode = $record['code'];
            $assetUrl = $record['url'];
            $assetTitle = $record['title'];

            $pathInfo = pathinfo(parse_url($assetUrl, PHP_URL_PATH));
            $imagePath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $pathInfo['basename'];

            try {
                $asset = $akeneoClient->getAssetManagerApi()->get('thumbnail', $assetCode);
                // asset exist already, do nothing
            } catch (NotFoundHttpException $e ) {
                // lets create it
                $akeneoClient->getAssetManagerApi()->upsert('thumbnail', $assetCode, [
                    'code' => $assetCode,
                    'values' => [
                        'label' => [[
                            'locale' => 'en_US', 'channel' => null, 'data' => $assetTitle
                        ]],
                        'thumbnail_url' => [[
                                'locale' => null, 'channel' => null, 'data' => $assetUrl
                        ]]
                    ]
                ]);

                try {
                    $mediaFileCode = $akeneoClient->getAssetMediaFileApi()->create($imagePath);
                    $akeneoClient->getAssetManagerApi()->upsert('thumbnail', $assetCode, [
                        'code' => $assetCode,
                        'values' => [
                            'thumbnail_image' => [[
                                'locale' => null, 'channel' => null, 'data' => $mediaFileCode
                            ]]
                        ]
                    ]);
                } catch(UploadAssetReferenceFileErrorException $exception) {
                    print_r($exception->getErrors());
                }
            }

            $progressBar->advance();
        }

        return Command::SUCCESS;
    })
    ->run();
