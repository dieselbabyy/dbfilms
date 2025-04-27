<?php

/**
 * This file is part of the Tmdb PHP API created by Michael Roterman.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @package Tmdb
 * @author Michael Roterman <michael@wtfz.net>
 * @copyright (c) 2013, Michael Roterman
 * @version 4.0.0
 */

namespace Tmdb\Model\Person;

use Tmdb\Model\Collection\People\PersonInterface;

/**
 * Class CastMember
 * @package Tmdb\Model\Person
 */
class CastMember extends AbstractMember implements PersonInterface
{
    public static $properties = [
        'id',
        'credit_id',
        'cast_id',
        'name',
        'character',
        'order',
        'profile_path'
    ];
    /**
     * @var string
     */
    private $character;
    /**
     * @var int
     */
    private $order;
    /**
     * @var mixed
     */
    private $castId;
    /**
     * @var mixed
     */
    private $creditId;

    /**
     * @return string
     */
    public function getCharacter()
    {
        return $this->character;
    }

    /**
     * @param string $character
     * @return self
     */
    public function setCharacter($character)
    {
        $this->character = $character;

        return $this;
    }

    /**
     * @return int
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param int $order
     * @return self
     */
    public function setOrder($order)
    {
        $this->order = (int)$order;

        return $this;
    }

    /**
     * @return int
     */
    public function getCastId()
    {
        return $this->castId;
    }

    /**
     * @param mixed $castId
     * @return self
     */
    public function setCastId($castId)
    {
        $this->castId = (int)$castId;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getCreditId()
    {
        return $this->creditId;
    }

    /**
     * @param mixed $creditId
     * @return self
     */
    public function setCreditId($creditId)
    {
        $this->creditId = $creditId;

        return $this;
    }
}
