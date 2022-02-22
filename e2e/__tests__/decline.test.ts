import { Browser, Page } from 'playwright';
import { getBrowser, closeBrowser, getStorePage, getScreenShotPath, setTestPrefix } from '../common/general';
import { buyStoreProduct, CheckoutFormData, declineEmail, fillCheckoutForm } from '../common/store';
import faker from '@faker-js/faker';
import { serverAddress } from '../e2e-config';
jest.setTimeout(5000000)
describe('Testing Decline Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser(true)
    });
    afterEach(async () => {
        await page.close();
        await closeBrowser()
    });
    it('Test General Decline Deal', async () => {
        setTestPrefix('braintree-geneal-decline')
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForTimeout(15000);
        const formData: CheckoutFormData = new CheckoutFormData(declineEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber())
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(2000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator('div[data-role="checkout-messages"]:visible');
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
        const title = await errorMsg.innerText()
        expect(title).toEqual("We are sorry, but we could not process your order at this time.");
    })

    it('Test With No Auth Card Decline Deal', async () => {
        setTestPrefix('braintree-noauth-decline')
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForTimeout(15000);
        const formData: CheckoutFormData = new CheckoutFormData(declineEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber(),
            '5105105105105100')
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(2000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator('div[data-role="checkout-messages"]:visible');
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
        const title = await errorMsg.innerText()
        expect(title).toEqual("We are sorry, but we could not process your order at this time.");
    })
})
///5105105105105100