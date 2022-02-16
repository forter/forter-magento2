import { Page } from 'playwright';
import { getScreenShotPath, scrollOnElement } from './general';
export const declineEmail = 'decline@forter.com'
export const acceptEmail = 'decline@forter.com'
export class CheckoutFormData {
    public email: string;
    public firstName: string;
    public lastName: string;
    public streetAddress: string;
    public country: string;
    public city: string;
    public zipcode: string;
    public phone: string;
    public readonly creditCardNumber = 4111111111111111;
    public readonly credisExpireMM = '03';
    public readonly credisExpireYY = '30';
    public readonly verifyCVC = 737;
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
    const product = productItem.nth(5);
    await product.hover();
    const addToCart = product.locator("button[type=submit]");
    await page.screenshot({ fullPage: true, path: getScreenShotPath('pre-add-to-cart') });
    await addToCart.dblclick();
    await page.waitForTimeout(2000);
    await scrollOnElement(page, '.showcart');
    await page.screenshot({ fullPage: true, path: getScreenShotPath('add-to-cart') });
}

export const fillCheckoutForm = async (page: Page, formData: CheckoutFormData) => {
    await page.screenshot({ fullPage: true, path: getScreenShotPath('pre-form-fill-step1') });
    const form = page.locator('.checkout-shipping-address')
    await form.locator('input[name="username"]').fill(formData.email);
    await form.locator('input[name="firstname"]').fill(formData.firstName);
    await form.locator('input[name="lastname"]').fill(formData.lastName);
    await form.locator('input[name="street[0]"]').fill(formData.streetAddress);
    await form.locator('select[name="region_id"]').selectOption({ label: 'Alabama' });
    await form.locator('input[name="city"]').fill(formData.city);
    await form.locator('input[name="postcode"]').fill(formData.zipcode)
    await form.locator('input[name="telephone"]').fill(formData.phone);
    await form.locator('input[name="telephone"]').fill(formData.phone);
    const radio = form.locator('input[type="radio"]')
    await radio.nth(0).click();
    await page.screenshot({ fullPage: true, path: getScreenShotPath('post-form-fill-step1') });

}