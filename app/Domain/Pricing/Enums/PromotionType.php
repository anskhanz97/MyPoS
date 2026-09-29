<?php

namespace App\Domain\Pricing\Enums;

enum PromotionType: string
{
    case ScheduledPrice = 'scheduled_price';
    case Coupon = 'coupon';
    case QuantityDiscount = 'quantity_discount';
    case MixAndMatch = 'mix_and_match';
    case BuyXGetY = 'buy_x_get_y';
}
