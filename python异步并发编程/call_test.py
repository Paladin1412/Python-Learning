import asyncio

def callback(sleep_times):
    print(sleep_times)

def stoploop(loop):
    loop.stop()
if __name__ == '__main__':
    loop=asyncio.get_event_loop()
    loop.call_soon(callback,2)
    loop.call_later(2,callback,1)#两秒中后执行一次callback（）
    loop.call_soon(stoploop,loop)
    loop.run_forever()