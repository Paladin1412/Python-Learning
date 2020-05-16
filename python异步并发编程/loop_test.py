import asyncio
import time
from functools import partial


async def get_html(url):
    print("start get url")
    # time.sleep(2) #异步编程禁止使用同步阻塞方式
    await asyncio.sleep(2)
    print("end get url")
    return "michael"


def callback(url, future):
    print(url)
    print("send to michael")


if __name__ == '__main__':
    start_time = time.time()
    loop = asyncio.get_event_loop()
    # tasks = [get_html("www.baidu.com") for i in range(100)]
    # loop.run_until_complete(asyncio.wait(tasks))//相当于join
    task = loop.create_task(get_html("www.baidu.com"))
    # task.add_done_callback(partial(callback,"www"))
    # loop.run_until_complete(task)
    # print(task.result())#获取协程的返回值
    # gather usage
    g1 = [get_html("www.baidu.com") for i in range(2)]
    g2 = [get_html("www.qq.com") for i in range(2)]
    print(g2, *g2)
    loop.run_until_complete(asyncio.gather(*g1, *g2))
    print(time.time() - start_time)
