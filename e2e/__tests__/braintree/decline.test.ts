import { Browser, Page } from 'playwright';
import faker from '@faker-js/faker';
import { declineEmail, TextOrderErrorMsg, PaymentType } from '../../common/constants';
import { CheckoutFormDataDto } from '../../common/dto/checkoutFormData.dto';
import { StoreDto } from '../../common/dto/store.dto';
import { getBrowser, closeBrowser, setTestPrefix, getStorePage, getScreenShotPath } from '../../common/general';
import { buyStoreProduct, fillCheckoutForm } from '../../common/store';
import { serverAddress } from '../../e2e-config';
jest.setTimeout(5000000)
describe('Testing Decline Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser()
    });
    afterEach(async () => {
        await closeBrowser()
    });
    it('Test General Decline Deal', async () => {
        setTestPrefix('braintree-geneal-decline')
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForTimeout(15000);
        const formData: CheckoutFormDataDto = new CheckoutFormDataDto(declineEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber(),
            PaymentType.BrainTree)
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(4000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator(StoreDto.Instance.OrderErrorMsgElmName);
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
        const title = await errorMsg.innerText()
        expect(title).toEqual(TextOrderErrorMsg);
    })
    it('Test With No Auth Card Decline Deal', async () => {
        setTestPrefix('braintree-noauth-decline')
        page = await getStorePage(serverAddress);
        await buyStoreProduct(page)
        page.goto(`${serverAddress}/checkout`)
        await page.waitForTimeout(15000);
        const formData: CheckoutFormDataDto = new CheckoutFormDataDto(declineEmail,
            faker.name.firstName(),
            faker.name.lastName(),
            faker.address.streetAddress(),
            faker.address.country(),
            faker.address.city(),
            faker.address.zipCode(),
            faker.phone.phoneNumber(),
            PaymentType.BrainTree,
            '5105105105105100')
        await fillCheckoutForm(page, formData);
        await page.waitForTimeout(4000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator(StoreDto.Instance.OrderErrorMsgElmName);
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
        const title = await errorMsg.innerText()
        expect(title).toEqual(TextOrderErrorMsg);
    })
})