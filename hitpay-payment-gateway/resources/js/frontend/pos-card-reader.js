function CardReaderSingle(props) {
    const terminalId = props.terminalId;
    const labelChange = props.labelChange;
    const mulitple = props.mulitple;
    let labelText = '';
    let myStyleClass  = 'hitpay-pos-card-reader-single';
    
    if (labelChange == "1") {
        labelText = ' - Terminal ID: '+terminalId;
    }

    if (mulitple == "1") {
        myStyleClass  = 'hitpay-pos-card-reader-multiple';
    }

    return (
        <>
            <label className={'woocommerce-form__label woocommerce-form__label-for-radio radio '+myStyleClass}  htmlFor={'hitpay_payment_option-'+terminalId}>
                <input id={'hitpay_payment_option-'+terminalId}
                       className="woocommerce-form__input woocommerce-form__input-radio input-radio"
                       type="radio"
                       name="hitpay_payment_option" 
                       value={terminalId} /> 
                <p style={{display: "inline"}}>Card Reader {labelText}</p>
            </label>
        </>
    );
}

function CardReaderMulitple(props) {
    const terminalIds = props.terminalIds;
    return (
        <>
        {terminalIds.map((terminalId, i) => <CardReaderSingle key={i} terminalId={terminalId} labelChange="1"  mulitple="1"  />)}
        </>
    );
}

export const CardReader = (props) => {
    const totalTerminals = props.totalTerminals;
    const terminalIds = props.terminalIds;
    if (totalTerminals == 1) {
        return <CardReaderSingle terminalId={terminalIds[0]} labelChange="0" mulitple="0" />;
    } else {
        return <CardReaderMulitple terminalIds={terminalIds} />;
    }
};