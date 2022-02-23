export class StoreDto {
    private static instance: StoreDto;
    public static get Instance() {
        if (!this.instance) {
            this.instance = new StoreDto;
        }
        return this.instance;
    }
    public get SelectProductItemElmName() {
        return 'li.product-item';
    }
    public get AddToCartBtnElmName() {
        return 'button[type=submit]';
    }
    public get ShowCartElmName() {
        return '.showcart';
    }

    public get OrderErrorMsgElmName() {
        return 'div[data-role="checkout-messages"]:visible'
    }

    public get OrderSuccessMsgElmName() {
        return 'span[data-ui-id="page-title-wrapper"]';
    }
    
    public get FetchSuccessOrderIdElmName() {
        return 'div.checkout-success >>  p:first-child >> span'
    }

}