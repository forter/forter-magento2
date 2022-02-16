import { Page } from 'playwright';
import { getScreenShotPath, scrollOnElement } from './general';
export const declineEmail = 'decline@forter.com'
export const acceptEmail = 'approve@forter.com'
export class CheckoutFormData {
    public email: string;
    public firstName: string;
    public lastName: string;
    public streetAddress: string;
    public country: string;
    public city: string;
    public zipcode: string;
    public phone: string;
    public readonly creditCardNumber = '4111111111111111';
    public readonly creditCardExpire = '03/2030';
    public readonly creditCardCVV = '737';
    constructor(email: string,
        firstName: string,
        lastName: string,
        streetAddress: string,
        country: string,
        city: string,
        zipcode: string,
        phone: string) {
        this.email = email;
        this.firstName = firstName;
        this.lastName = lastName;
        this.country = country;
        this.streetAddress = streetAddress;
        this.city = city;
        this.zipcode = zipcode;
        this.phone = phone
    }

}
export const buyStoreProduct = async (page: Page) => {
    const productItem = page.locator('li.product-item')
    const product = productItem.nth(4);
    await product.hover();
    const addToCart = product.locator("button[type=submit]");
    await page.screenshot({ fullPage: true, path: getScreenShotPath('pre-add-to-cart') });
    await addToCart.dblclick();
    await page.waitForTimeout(2000);
    await scrollOnElement(page, '.showcart');
    await page.screenshot({ fullPage: true, path: getScreenShotPath('add-to-cart') });
    console.log("finshed checkout page");
}

export const fillCheckoutForm = async (page: Page, formData: CheckoutFormData) => {
    await fillCheckoutFirstPage(page, formData);
    await page.waitForTimeout(15000);
    await fillCheckoutLastPage(page, formData);
}

const fillCheckoutFirstPage = async (page: Page, formData: CheckoutFormData) => {
    const form = page.locator('.opc-wrapper')
    await form.screenshot({ path: getScreenShotPath('pre-form-shipping-address') });
    await form.locator('input[name="username"]').fill(formData.email);
    await form.locator('input[name="firstname"]').fill(formData.firstName);
    await form.locator('input[name="lastname"]').fill(formData.lastName);
    await form.locator('input[name="street[0]"]').fill(formData.streetAddress);
    await form.locator('select[name="region_id"]').selectOption({ label: 'Alabama' });
    await form.locator('input[name="city"]').fill(formData.city);
    await form.locator('input[name="postcode"]').fill(formData.zipcode)
    await form.locator('input[name="telephone"]').fill(formData.phone);
    await form.locator('input[type="radio"]').nth(0).click();
    await form.screenshot({ path: getScreenShotPath('post-form-shipping-address') });
    await form.locator('button[data-role="opc-continue"]').click();
    console.log("finshed shipping address");
}
const fillCheckoutLastPage = async (page: Page, formData: CheckoutFormData) => {
    const form = page.locator('#checkout-step-payment')
    await form.screenshot({ path: getScreenShotPath('pre-form-place-order') });
    await form.locator('input#braintree').click();
    await page.waitForTimeout(10000);
    await form.locator('input#credit-card-number').fill(formData.creditCardNumber);
    await form.locator('input#expiration').fill(formData.creditCardExpire)
    await form.locator('input#cvv').fill(formData.creditCardCVV);
    await form.screenshot({ path: getScreenShotPath('post-form-place-order') });
    await form.locator('button[title="Place Order"]').click();
    console.log("finshed place order");
}