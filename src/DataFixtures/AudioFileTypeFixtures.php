<?php

namespace App\DataFixtures;

use App\Entity\AudioFileType;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Bundle\FixturesBundle\Fixture;

class AudioFileTypeFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $types = [
            'Instrumental',
            'Guitar',
            'Vocals',
            'Bass',
            'Master',
            'Other'
        ];

        foreach ($types as $typeName) {
            $audioFileType = new AudioFileType();
            $audioFileType->setName($typeName);
            $manager->persist($audioFileType);
        }
        $manager->flush();
    }
}
