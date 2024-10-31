<?php

namespace ZPOS\Structure;

use WC_Order;

trait OrderPermissions
{
	public function add_extended_permissions_to_order(array $allcaps, array $caps, array $args): array
	{
		if (
			(!in_array('read_shop_order', $args, true) && !in_array('edit_shop_order', $args, true)) ||
			empty($args[1]) ||
			empty($args[2])
		) {
			return $allcaps;
		}

		$order_id = is_numeric($args[2]) ? intval($args[2]) : 0;
		$order = wc_get_order($order_id);

		if (!$order instanceof WC_Order || empty($order->get_meta('_pos_by'))) {
			return $allcaps;
		}

		$user_id = is_numeric($args[1]) ? intval($args[1]) : 0;
		$author_id = 0 < $order_id ? intval(get_post($order_id)->post_author) : 0;

		if ($user_id !== $author_id) {
			return $allcaps;
		}

		$allcaps['edit_posts'] = true;
		$allcaps['read_shop_order'] = true;
		$allcaps['edit_shop_order'] = true;

		return $allcaps;
	}
}
