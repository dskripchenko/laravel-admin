import { describe, it, expect, vi } from 'vitest'
import { createAdminClient } from './client'
import { UnauthenticatedError } from './errors'

/**
 * 401 обязан уводить на форму входа.
 *
 * Обработчик жил в опциях клиента с самого начала и НИКЕМ не передавался:
 * отказ просто отбрасывался. Протухшая сессия оставляла человека в живом
 * каркасе с пустым меню — оболочка уже была в браузере, а меню и манифест
 * приходили отказом. Помогала вторая перезагрузка, о чём догадаться неоткуда.
 */
describe('createAdminClient: 401', () => {
  it('зовёт onUnauthenticated и всё равно отклоняет промис', async () => {
    const onUnauthenticated = vi.fn()
    const client = createAdminClient({ baseURL: '/api', onUnauthenticated })

    // Перехватываем транспорт: интересует ветка обработки ответа, а не сеть.
    client.raw.interceptors.request.use(() => {
      const err = new Error('401') as Error & { response?: unknown; config?: unknown }
      err.response = { status: 401, data: { payload: { errorKey: 'unauthenticated', message: 'no' } } }
      throw err
    })

    await expect(client.raw.get('/whatever')).rejects.toBeInstanceOf(UnauthenticatedError)
    expect(onUnauthenticated).toHaveBeenCalledTimes(1)
  })

  it('без обработчика ошибка ведёт себя как прежде', async () => {
    const client = createAdminClient({ baseURL: '/api' })
    client.raw.interceptors.request.use(() => {
      const err = new Error('401') as Error & { response?: unknown }
      err.response = { status: 401, data: { payload: { errorKey: 'unauthenticated', message: 'no' } } }
      throw err
    })

    await expect(client.raw.get('/whatever')).rejects.toBeInstanceOf(UnauthenticatedError)
  })
})
