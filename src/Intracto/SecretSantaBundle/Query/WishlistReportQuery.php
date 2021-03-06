<?php

namespace Intracto\SecretSantaBundle\Query;

use Doctrine\DBAL\Driver\Connection;

class WishlistReportQuery
{
    /** @var Connection */
    private $dbal;
    /** @var ParticipantReportQuery */
    private $participantReportQuery;

    /**
     * @param Connection             $dbal
     * @param ParticipantReportQuery $participantReportQuery
     */
    public function __construct(Connection $dbal, ParticipantReportQuery $participantReportQuery)
    {
        $this->dbal = $dbal;
        $this->participantReportQuery = $participantReportQuery;
    }

    /**
     * @param Season $season
     *
     * @return float
     */
    public function calculateCompletedWishlists(Season $season)
    {
        $wishlistCount = $this->countWishlists($season);
        $participantCount = $this->participantReportQuery->countParticipants($season);

        if ($participantCount === 0) {
            throw new NoResultException();
        }

        return (implode($wishlistCount[0]) / $participantCount) * 100;
    }

    /**
     * @param Season $season
     *
     * @return mixed
     */
    private function countWishlists(Season $season)
    {
        $query = $this->dbal->createQueryBuilder()
            ->select('count(p.id) AS wishlistCount')
            ->from('party', 'p')
            ->innerJoin('p', 'participant', 'e', 'p.id = e.party_id')
            ->where('p.sent_date >= :firstDay')
            ->andWhere('p.sent_date < :lastDay')
            ->andWhere('e.wishlist_updated = TRUE')
            ->setParameter('firstDay', $season->getStart()->format('Y-m-d H:i:s'))
            ->setParameter('lastDay', $season->getEnd()->format('Y-m-d H:i:s'));

        return $query->execute()->fetchAll();
    }

    /**
     * @param \DateTime $date
     *
     * @return float
     */
    public function calculateCompletedWishlistsUntilDate(\DateTime $date)
    {
        $totalWishlists = $this->countAllWishlistsUntilDate($date);
        $totalParticipants = $this->participantReportQuery->countAllParticipantsUntilDate($date);

        if ($totalParticipants[0]['totalParticipantCount'] != 0) {
            return (implode($totalWishlists[0]) / implode($totalParticipants[0])) * 100;
        }

        throw new NoResultException();
    }

    /**
     * @param \DateTime $date
     *
     * @return mixed
     */
    private function countAllWishlistsUntilDate(\DateTime $date)
    {
        $query = $this->dbal->createQueryBuilder()
            ->select('count(p.id) AS totalWishlistCount')
            ->from('party', 'p')
            ->innerJoin('p', 'participant', 'e', 'p.id = e.party_id')
            ->where('p.sent_date < :lastDay')
            ->andWhere('e.wishlist_updated = TRUE')
            ->setParameter('lastDay', $date->format('Y-m-d H:i:s'));

        return $query->execute()->fetchAll();
    }

    /**
     * @param Season $season1
     * @param Season $season2
     *
     * @return float
     */
    public function calculateCompletedWishlistDifferenceBetweenSeasons(Season $season1, Season $season2)
    {
        $completedWishlistsSeason1 = $this->calculateCompletedWishlists($season1);
        try {
            $completedWishlistsSeason2 = $this->calculateCompletedWishlists($season2);
        } catch (\Exception $e) {
            return $completedWishlistsSeason1;
        }

        return $completedWishlistsSeason1 - $completedWishlistsSeason2;
    }
}
