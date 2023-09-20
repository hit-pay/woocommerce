import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

import { CheckoutHandler } from './checkout-handler';
import { PosForm } from './pos-form';
import { getHitpayServerData } from './utils';

const ContentComponent = ( { emitResponse, eventRegistration } ) => {
	const {
            description = '',
	} = getHitpayServerData();
        
        const isDropInEnabled = getHitpayServerData().drop_in_enabled;
        const isPosEnabled = getHitpayServerData().pos_enabled;

	return (
		<>
                    { isDropInEnabled &&
			<CheckoutHandler
				emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
			/>
                    }
                    
                    { decodeEntities( description ) }
                    
                    { isPosEnabled &&
                        <PosForm 
                                emitResponse={ emitResponse }
				eventRegistration={ eventRegistration }
                        />
                    }
                    
                    <div id="hitpay_background_layer"></div>

		</>
	);
};

export default ContentComponent;