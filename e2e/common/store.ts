import { Page } from 'playwright';
import { CheckoutFormDto } from './dto/checkoutForm.dto';
import { CheckoutFormDataDto } from './dto/checkoutFormData.dto';
import { StoreDto } from './dto/store.dto';
import { getScreenShotPath, scrollOnElement } from './general';
export const buyStoreProduct = async (page: Page) => {
    const productItem = page.locator(StoreDto.Instance.SelectProductItemElmName)
    const product = productItem.nth(4);
    await product.hover();
    const addToCart = product.locator(StoreDto.Instance.AddToCartBtnElmName);
    await page.screenshot({ fullPage: true, path: getScreenShotPath('pre-add-to-cart') });
    await addToCart.dblclick();
    await page.waitForTimeout(2000);
    await scrollOnElement(page, StoreDto.Instance.ShowCartElmName);
    await page.screenshot({ fullPage: true, path: getScreenShotPath('add-to-cart') });
    console.log("finshed shopping page and did checkout");
}

export const fillCheckoutForm = async (page: Page, formData: CheckoutFormDataDto) => {
    await fillCheckoutFirstPage(page, formData);
    await page.waitForTimeout(5000);
    await fillCheckoutLastPage(page, formData);
}

const fillCheckoutFirstPage = async (page: Page, formData: CheckoutFormDataDto) => {
    let checkoutForm: CheckoutFormDto = new CheckoutFormDto(formData.payment);
    const form = page.locator(checkoutForm.FormElmName)
    await form.screenshot({ path: getScreenShotPath('pre-form-shipping-address') });
    await form.locator(checkoutForm.InputEmailElmName).fill(formData.email);
    await form.locator(checkoutForm.InputFirstNameElmName).fill(formData.firstName);
    await form.locator(checkoutForm.InputLastNameElmName).fill(formData.lastName);
    await form.locator(checkoutForm.InputStreetElmName).fill(formData.streetAddress);
    await form.locator(checkoutForm.InputStateElmName).selectOption({ label: 'Alabama' });
    await form.locator(checkoutForm.InputCityElmName).fill(formData.city);
    await form.locator(checkoutForm.InputZipCodeElmName).fill(formData.zipcode)
    await form.locator(checkoutForm.InputPhoneElmName).fill(formData.phone);
    await form.locator(checkoutForm.InputShippingTypeElmName).nth(0).click();
    await form.screenshot({ path: getScreenShotPath('post-form-shipping-address') });
    await form.locator(checkoutForm.PlaceOrderElmName).click();
    console.log("finshed shipping address");
}
const fillCheckoutLastPage = async (page: Page, formData: CheckoutFormDataDto) => {
    const checkoutForm: CheckoutFormDto = new CheckoutFormDto(formData.payment);
    const paymentForm= checkoutForm.PaymentForm;
    await page.screenshot({ path: getScreenShotPath('pre-form-place-order') });
    await page.locator(paymentForm.getSelectPaymentType()).click();
    await page.waitForTimeout(5000);
    await page.screenshot({ path: getScreenShotPath('cardform-form-place-order') });
    let iframe_element = await page.waitForSelector(paymentForm.getPaymentIFrameCreditNum())
    let iframe = await iframe_element.contentFrame()
    await iframe?.fill(paymentForm.getCreditCardNum(), formData.creditCardNumber);
    iframe_element = await page.waitForSelector(paymentForm.getPaymentIFrameCreditExp())
    iframe = await iframe_element.contentFrame()
    await iframe?.fill(paymentForm.getCreditCardExp(), formData.creditCardExpire)
    iframe_element = await page.waitForSelector(paymentForm.getPaymentIFrameCreditCVV())
    iframe = await iframe_element.contentFrame()
    await iframe?.fill(paymentForm.getCreditCardCVV(), formData.creditCardCVV);
    await page.screenshot({ path: getScreenShotPath('post-form-place-order') });
    const button = page.locator(checkoutForm.PlaceOrderElmName).nth(0);
    await button.click();
    console.log("finshed place order");
}