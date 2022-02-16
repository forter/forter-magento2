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
    public zipcode: number;
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
        zipcode: number,
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
    const product = productItem.nth(0);
    await product.hover();
    const productSizes = product.locator('.swatch-option')
    await productSizes.nth(0).click();
    const addToCart = product.locator("button.tocart");
    await page.screenshot({ fullPage: true, path: getScreenShotPath('pre-add-to-cart') });
    await addToCart.dblclick();
    await page.screenshot({ fullPage: true, path: getScreenShotPath('post-add-to-cart') });
    await page.waitForTimeout(2000);
    await scrollOnElement(page, '.showcart');
    await page.screenshot({ fullPage: true, path: getScreenShotPath('add-to-cart') });
}

export const fillCheckoutForm = async (page: Page, formData: CheckoutFormData) => {

}