import { PaymentType } from '../constants';
import { ICreditFormInput } from './ICreditFormInput';
import { PaymentBrainTree } from './payments/brainTree.dto';
export class CheckoutFormDto {
    public readonly Payment:PaymentType;
    private paymentFormElem:ICreditFormInput = new PaymentBrainTree();
    constructor(payment: PaymentType) {
        this.Payment = payment;
        switch(this.Payment) {
            case PaymentType.BrainTree:
                this.paymentFormElem = new PaymentBrainTree()
        }
    }
    
    public get FormElmName() {
        return '.opc-wrapper'
    }
    public get InputEmailElmName() {
        return 'input[name="username"]';
    }
    public get InputFirstNameElmName() {
        return 'input[name="firstname"]';
    }
    public get InputLastNameElmName() {
        return 'input[name="lastname"]';
    }
    public get InputStreetElmName() {
        return 'input[name="street[0]"]';
    }
    public get InputCountryElmName() {
        return 'input[name="city"]';
    }
    public get InputCityElmName() {
        return 'input[name="city"]';
    }
    public get InputStateElmName() {
        return 'select[name="region_id"]';
    }
    public get InputZipCodeElmName() {
        return 'input[name="postcode"]';
    }

    public get InputPhoneElmName() {
        return 'input[name="telephone"]';
    }

    public get InputShippingTypeElmName() {
        return 'input[type="radio"]';
    }
    public get SubmitShippingFormElmName() {
        return 'button[data-role="opc-continue"]';
    }

    public get PaymentForm() {
        return this.paymentFormElem;
    }

    public get PlaceOrderElmName() {
        return 'button[title="Place Order"]'
    }


}