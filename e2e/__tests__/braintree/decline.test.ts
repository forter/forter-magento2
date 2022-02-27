import { Browser, Page } from 'playwright';
import faker from '@faker-js/faker';
import { declineEmail, TextOrderErrorMsg, PaymentType, ForterFlowMode, TextOrderSuccessMsg } from '../../common/constants';
import { CheckoutFormDataDto } from '../../common/dto/checkoutFormData.dto';
import { StoreDto } from '../../common/dto/store.dto';
import { getBrowser, closeBrowser, setTestPrefix, getStorePage, getScreenShotPath } from '../../common/general';
import { buyStoreProduct, fetchOrderIdFromPage, fillCheckoutForm } from '../../common/store';
import { serverAddress } from '../../e2e-config';
import { changeForterMode, checkForOrderByName, checkOrderPage, checkStatusOfOrderOnOrderList, doStoreAdminLogin } from '../../common/store-admin';
jest.setTimeout(5000000)
describe('BrainTree Decline Deals', () => {
    let browser: Browser;
    let page: Page;
    beforeEach(async () => {
        browser = await getBrowser()
    });
    afterEach(async () => {
        await closeBrowser()
    });
    it('BrainTree Decline Deal, Verify Mode: Before', async () => {
        setTestPrefix('braintree-decline-before')
        page = await changeForterMode(page, ForterFlowMode.Before);
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
        await page.waitForTimeout(3000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator(StoreDto.Instance.OrderErrorMsgElmName);
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
        const title = await errorMsg.innerText()
        expect(title).toEqual(TextOrderErrorMsg);
    })
    it('BrainTree With No Auth Card Decline Deal', async () => {
        setTestPrefix('braintree-decline-noauth-before')
        page = await changeForterMode(page, ForterFlowMode.Before);
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
        await page.waitForTimeout(3000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator(StoreDto.Instance.OrderErrorMsgElmName);
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
        const title = await errorMsg.innerText()
        expect(title).toEqual(TextOrderErrorMsg);
    })


    it('BrainTree Decline Deal, Verify Mode: After', async () => {
        setTestPrefix('braintree-decline-after')
        page = await changeForterMode(page, ForterFlowMode.After);
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
        await page.waitForTimeout(5000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const title = await page.locator(StoreDto.Instance.OrderSuccessMsgElmName).innerText()
        expect(title).toEqual(TextOrderSuccessMsg);
        const orderID = await fetchOrderIdFromPage(page);
        expect(orderID).not.toHaveLength(0);
        console.log(`user buy under order id (${orderID})`)
        page = await getStorePage(`${serverAddress}/admin`);
        page = await doStoreAdminLogin(page);
        await checkStatusOfOrderOnOrderList(page,orderID, false)
        await checkOrderPage(page,orderID, false)
    })

    it('BrainTree Decline Deal, Verify Mode: Before & After', async () => {
        setTestPrefix('braintree-decline-before-after')
        page = await changeForterMode(page, ForterFlowMode.BeforeAndAfter);
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
        await page.waitForTimeout(3000);
        await page.screenshot({ fullPage: true, path: getScreenShotPath('decline-deal-final-result') });
        const errorMsg = page.locator(StoreDto.Instance.OrderErrorMsgElmName);
        const errorMsgVisible = await errorMsg.isVisible();
        expect(errorMsgVisible).toBeTruthy();
        const title = await errorMsg.innerText()
        expect(title).toEqual(TextOrderErrorMsg);
    })

    it('')
})