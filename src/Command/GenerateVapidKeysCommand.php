<?php

namespace App\Command;

use Minishlink\WebPush\VAPID;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-vapid-keys',
    description: 'Generate VAPID keys for push notifications',
)]
class GenerateVapidKeysCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        try {
            $vapidKeys = VAPID::createVapidKeys();

            $io->success('VAPID keys generated successfully!');
            $io->text('Add these lines to your .env file: ');
            $io->newLine();
            $io->text("VAPID_PUBLIC_KEY={$vapidKeys['publicKey']}");
            $io->text("VAPID_PRIVATE_KEY={$vapidKeys['privateKey']}");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error generating keys: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
