import { ProcessingFormHandler } from './processing-form-handler';
import { OnlinePayment } from './pos-online-payment';
import { CardReader } from './pos-card-reader';
import { getHitpayServerData } from './utils';

export const PosForm = ( {
    emitResponse, 
    eventRegistration
} ) => {
    const terminalIds = getHitpayServerData().terminal_ids;
    const totalTerminals = getHitpayServerData().total_terminals;
    
    return (
        <>
            <ProcessingFormHandler
                    emitResponse={ emitResponse }
                    eventRegistration={ eventRegistration }
            />
            <div className="hitpay-payment-selection">
                <OnlinePayment totalTerminals ={totalTerminals} />
                <CardReader totalTerminals ={totalTerminals} terminalIds ={terminalIds} />
            </div>
        </>
     );
};