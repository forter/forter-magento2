import { ICreditFormInput } from '../ICreditFormInput';
export class PaymentBrainTree implements ICreditFormInput {
    public getSelectPaymentType= () => 'input[value=braintree]';
    public getPaymentIFrameCreditNum = () => '#braintree-hosted-field-number'
    public getCreditCardNum = () => 'input[name="credit-card-number"]'
    public getPaymentIFrameCreditExp = () => '#braintree-hosted-field-expirationDate'
    public getCreditCardExp = () => 'input[name="expiration"]'
    public getPaymentIFrameCreditCVV = () => '#braintree-hosted-field-cvv'
    public getCreditCardCVV = () => 'input[name="cvv"]'
}