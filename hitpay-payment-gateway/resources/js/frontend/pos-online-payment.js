export const OnlinePayment = (props) => {
    const totalTerminals = props.totalTerminals;
    let myStyleClass  = 'hitpay-pos-online-multiple';
    
    if (totalTerminals == "1") {
        myStyleClass  = 'hitpay-pos-online-single';
    }
    return (
        <>
            <label className={'woocommerce-form__label woocommerce-form__label-for-radio radio '+ myStyleClass} htmlFor="hitpay_payment_option-0">
                <input id="hitpay_payment_option-0"
                       className="woocommerce-form__input woocommerce-form__input-radio input-radio"
                       type="radio"
                       name="hitpay_payment_option" 
                       value="onlinepayment" defaultChecked="true" /> 
                       <p style={{display: "inline"}}>Online Payments</p>
            </label>
        </>
     );
};