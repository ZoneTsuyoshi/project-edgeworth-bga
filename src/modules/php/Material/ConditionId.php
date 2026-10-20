<?php

/**
 * © だんじり協奏曲 (Danjiri Concerto)
 * https://x.com/danjiriconcerto
 */

declare(strict_types=1);

namespace Bga\Games\projectedgeworth\Material;

/**
 * 条件（011 §2-6、K01〜K13）。
 */
enum ConditionId: string
{
    case EventSatellite = 'EVENT_SATELLITE'; // K01
    case EventCardType = 'EVENT_CARD_TYPE'; // K02
    case FullTypes = 'FULL_TYPES'; // K03
    case LinkAreaRegion = 'LINK_AREA_REGION'; // K04
    case LinkAreaKind = 'LINK_AREA_KIND'; // K05
    case BelowRegion = 'BELOW_REGION'; // K06
    case EnteredOwnLinkedArea = 'ENTERED_OWN_LINKED_AREA'; // K07
    case RegionChanged = 'REGION_CHANGED'; // K08
    case LandedAreaKind = 'LANDED_AREA_KIND'; // K09
    case NoActionOverlap = 'NO_ACTION_OVERLAP'; // K10
    case IsBonusHolder = 'IS_BONUS_HOLDER'; // K11
    case OwnActionChoice = 'OWN_ACTION_CHOICE'; // K12
    case ActionIsRunning = 'ACTION_IS_RUNNING'; // K13
}
