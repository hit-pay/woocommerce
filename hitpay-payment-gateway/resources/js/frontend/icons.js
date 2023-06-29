import { getHitpayServerData } from './utils';

export const geHitpayIcons = () => {
    return Object.entries( getHitpayServerData().icons ).map(
        ( [ id, { src, alt } ] ) => {
            return {
                id,
                src,
                alt,
            };
        }
    );
};