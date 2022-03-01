import { Page } from 'playwright';
import { CheckoutFormDataDto } from '../checkoutFormData.dto';
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
export const adyenFillCreditInfo =  async (page: Page,paymentForm:ICreditFormInput,formData: CheckoutFormDataDto) => {
    let iframe_element = await page.waitForSelector(paymentForm.getPaymentIFrameCreditNum())
    let iframe = await iframe_element.contentFrame()
    await iframe?.fill(paymentForm.getCreditCardNum(), formData.creditCardNumber);
    iframe_element = await page.waitForSelector(paymentForm.getPaymentIFrameCreditExp())
    iframe = await iframe_element.contentFrame()
    await iframe?.fill(paymentForm.getCreditCardExp(), formData.creditCardExpire)
    iframe_element = await page.waitForSelector(paymentForm.getPaymentIFrameCreditCVV())
    iframe = await iframe_element.contentFrame()
    await iframe?.fill(paymentForm.getCreditCardCVV(), formData.creditCardCVV);
}