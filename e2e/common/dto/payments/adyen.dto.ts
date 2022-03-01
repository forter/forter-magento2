import { Page } from 'playwright';
import { CheckoutFormDataDto } from '../checkoutFormData.dto';
import { ICreditFormInput } from '../ICreditFormInput';
export class PaymentAdyenTree implements ICreditFormInput {
    public getSelectPaymentType= () => 'input[value=adyen_cc]';
    public getPaymentIFrameCreditNum = () => 'iframe[title="Iframe for secured card number"]'
    public getCreditCardNum = () => 'input[data-fieldtype="encryptedCardNumber"]'
    public getPaymentIFrameCreditExp = () => 'iframe[title="Iframe for secured card expiry date"]'
    public getCreditCardExp = () => 'input[data-fieldtype="encryptedExpiryDate"]'
    public getPaymentIFrameCreditCVV = () => 'iframe[title="Iframe for secured card security code"]'
    public getCreditCardCVV = () => 'input[data-fieldtype="encryptedSecurityCode"]'
    public getCreditCardHolderName = () => 'input[placeholder="J. Smith"]'
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
    await page.fill(paymentForm.getCreditCardHolderName(), `${formData.firstName} ${formData.lastName}`)
}