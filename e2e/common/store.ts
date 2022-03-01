import { Page } from 'playwright';
import { CheckoutFormDto } from './dto/checkoutForm.dto';
import { CheckoutFormDataDto } from './dto/checkoutFormData.dto';
import { brainTreeFillCreditInfo } from './dto/payments/brainTree.dto';
import { adyenFillCreditInfo } from './dto/payments/adyen.dto';
import { StoreDto } from './dto/store.dto';
import { getScreenShotPath, scrollOnElement } from './general';
import { PaymentType } from './constants';
export const buyStoreProduct = async (page: Page) => {
    const productItem = page.locator(StoreDto.Instance.SelectProductItemElmName)
    const product = productItem.nth(4);
    await product.hover();
    const addToCart = product.locator(StoreDto.Instance.AddToCartBtnElmName);
    await page.screenshot({ fullPage: true, path: getScreenShotPath('pre-add-to-cart') });
    await addToCart.dblclick();
    await page.waitForLoadState('networkidle')
    await scrollOnElement(page, StoreDto.Instance.ShowCartElmName);
    await page.screenshot({ fullPage: true, path: getScreenShotPath('add-to-cart') });
    console.log("finshed shopping page and did checkout");
}

export const fillCheckoutForm = async (page: Page, formData: CheckoutFormDataDto) => {
    await fillCheckoutFirstPage(page, formData);
    await page.waitForLoadState('networkidle')
    await fillCheckoutLastPage(page, formData);
}

export const fetchOrderIdFromPage = async (page: Page) => {
    const orderTextElm = page.locator(StoreDto.Instance.FetchSuccessOrderIdElmName);
    const orderText = await orderTextElm.textContent();
    const matches = orderText?.match(/\d/g);
    expect(matches).toBeDefined();
    const orderId = matches?.join('')
    return orderId || '';
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
    await form.locator(checkoutForm.SubmitShippingFormElmName).click();
    console.log("finshed shipping address");
}
const fillCheckoutLastPage = async (page: Page, formData: CheckoutFormDataDto) => {
    const checkoutForm: CheckoutFormDto = new CheckoutFormDto(formData.payment);
    const paymentForm= checkoutForm.PaymentForm;
    await page.screenshot({ path: getScreenShotPath('pre-form-place-order') });
    await page.locator(paymentForm.getSelectPaymentType()).click();
    await page.screenshot({ path: getScreenShotPath('cardform-form-place-order') });

    let button = page.locator(checkoutForm.PlaceOrderElmName)
    switch (formData.payment) {
        case PaymentType.BrainTree:
            await page.waitForLoadState('networkidle');
            await brainTreeFillCreditInfo(page, paymentForm, formData);
            button = button.nth(0);
            break;
        case PaymentType.Adyen:
            await page.waitForTimeout(20000);
            await page.screenshot({ fullPage:true ,path: getScreenShotPath('cardform-adyen-form-place-order') });
            await adyenFillCreditInfo(page, paymentForm, formData);
            button = button.nth(2);
            break;
    }
    await button.click();
    console.log("finshed place order");
}