/**
 * External dependencies
 */
import { getSetting } from '@woocommerce/settings';

/**
 * Hitpay data comes form the server passed on a global object.
 */
export const getHitpayServerData = () => {
	const hitpayServerData = getSetting( 'hitpay_data', null );
	if ( ! hitpayServerData || typeof hitpayServerData !== 'object' ) {
		throw new Error( 'Hitpay initialization data is not available' );
	}
	return hitpayServerData;
};