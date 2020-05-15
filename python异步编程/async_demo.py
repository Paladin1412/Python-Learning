"""
异步：遇到阻塞，会直接执行第二个事物，不会等待
"""
import time

now = lambda: time.time()


def foo():
    time.sleep(1)


start = now()
[foo() for i in range(5)]
print((now() - start))

import asyncio


async def foo():
    await asyncio.sleep()


start = now()
loop = asyncio.get_event_loop()
for i in range(5):
    loop.run_until_complete(foo())
print((now() - start))
