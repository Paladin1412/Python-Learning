# import asyncio
# import subprocess
# async def do_work(x):
#     print("waiting:",x)
#
# #调用协程
# co1 = do_work(3)
# #创建事件循环
# loop=asyncio.get_event_loop()
# #创建任务
# task = asyncio.ensure_future(co1)
# #将协程对象注册到事件循环中
# loop.run_until_complete(task)
#
#
# async def call_sys( command):
#     await  subprocess.Popen(command, shell=True, stdout=subprocess.PIPE)
#     return ret.stdout.read().decode('utf-8')
import asyncio

# async def async_procedure():
#     process = await asyncio.create_subprocess_exec('ping', "-c","2",'baidu.com')
#     await process.wait()
#     print('async procedure done.')


async def do_work(x):
    print("waiting",x)
    await asyncio.sleep(x)
    return 'Don after{}s'.format(x)

co1 = do_work(1)
co2 = do_work(2)
co3 = do_work(4)

tasks = [asyncio.ensure_future(co1),asyncio.ensure_future(co2),asyncio.ensure_future(co3)]
loop = asyncio.get_event_loop()
loop.run_until_complete(asyncio.wait(tasks))

for task in tasks:
    print("ret",task.result())

#协程嵌套
async def do_work(x):
    print("waiting", x)
    await asyncio.sleep(x)
    return 'Don after{}s'.format(x)
async def main():
    # 封装任务列表
    # 创建多个协程对象
    co1 = do_work(1)
    co2 = do_work(2)
    co3 = do_work(4)

    tasks = [asyncio.ensure_future(co1), asyncio.ensure_future(co2), asyncio.ensure_future(co3)]
    done,pending = await asyncio.wait(tasks)
    for task in done:
        print(task.result())
loop = asyncio.get_event_loop()
loop.run_until_complete(main())