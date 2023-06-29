
import { sprintf, __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import { decodeEntities } from '@wordpress/html-entities';

import ContentComponent from './component';
import { getHitpayServerData } from './utils';
import { geHitpayIcons } from './icons';

const defaultLabel = __(
	'Hitpay Payment Gateway',
	'woo-gutenberg-products-block'
);

const label = decodeEntities( getHitpayServerData().title ) || defaultLabel;
/**
 * Content component
 */
const Content = () => {
	return decodeEntities( getHitpayServerData().description || '' );
};
/**
 * Label component
 *
 * @param {*} props Props from payment API.
 */
const Label = ( props ) => {
	const { PaymentMethodLabel } = props.components;
	return <PaymentMethodLabel text={ label } />;
};

const HitpayComponent = ( { RenderedComponent, ...props } ) => {
	return <RenderedComponent { ...props } />;
};

const cardIcons = geHitpayIcons();

/**
 * HitPay payment method config object.
 */
const HitPay = {
	name: "hitpay",
	label: <Label />,
	content: <HitpayComponent RenderedComponent={ ContentComponent }/>,
	edit: <HitpayComponent RenderedComponent={ ContentComponent }/>,
        icons: cardIcons,
	canMakePayment: () => true,
	ariaLabel: label,
	supports: {
		features: getHitpayServerData().supports,
	},
};

registerPaymentMethod( HitPay );
