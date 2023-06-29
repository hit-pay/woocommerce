import { useEffect } from '@wordpress/element';
import { getHitpayServerData } from './utils';

export const ProcessingFormHandler = ( {
	eventRegistration,
	emitResponse,
} ) => {
	const {
        onPaymentProcessing,
	} = eventRegistration;
        
    useEffect( () => {
		const unsubscribe = onPaymentProcessing( async () => {

			const hitpay_payment_option = jQuery('input[name="hitpay_payment_option"]:checked').val();

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: {
                    paymentMethodData: {
                        hitpay_payment_option,
                    },
                },
            };

			return {
				type: emitResponse.responseTypes.ERROR,
				message: 'There was an error',
			};
		} );

		return () => {
			unsubscribe();
		};
	}, [
		emitResponse.responseTypes.ERROR,
		emitResponse.responseTypes.SUCCESS,
		onPaymentProcessing,
	] );   
};