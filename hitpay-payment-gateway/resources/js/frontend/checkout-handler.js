import { useEffect } from '@wordpress/element';
import { getHitpayServerData } from './utils';

export const CheckoutHandler = ( {
	eventRegistration,
	emitResponse,
} ) => {
	const {
            onCheckoutSuccess,
	} = eventRegistration;
        
        const isDropInEnabled = getHitpayServerData().drop_in_enabled;
        
        const displayDropInPopup = ( checkoutResponse ) => {
            if (!isDropInEnabled) {
                return true;
            }
            
            const { processingResponse } = checkoutResponse;
            
            return interceptHitpayBlockPlaceOrder(processingResponse, emitResponse);

        };
        
        useEffect( () => {
		const unsubscribe = onCheckoutSuccess( displayDropInPopup );
		return unsubscribe;
	}, [ onCheckoutSuccess ] );
        
	return null;
};