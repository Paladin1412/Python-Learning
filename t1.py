# import time
# def step():
#     print("->now:")
#
#     a = yield f()
#     print("->now",a)
#     a = yield 8
#     print("->now",a)
#     a = yield 8
#     print("->now",a)
# def f():
#     time.sleep(2)
#     return 4
# s = step()
# s.send(None)
# print(next(s))
#
#
# l1=[1,2,3,4]
# print(*l1)

# l2=[]
#
# r= "".join(l2)
# print(r)
import subprocess
#
# c = subprocess.check_output(' adb shell dumpsys SurfaceFlinger --latency com.xiaodianshi.tv.yst/com.xiaodianshi.tv.yst.ui.bangumi.BangumiDetailActivity',shell=True)
# results = c.decode('utf-8').split('\r\n')
# print(1e9)
# timestamps = []
# for line in results[1:]:
#     tmp_fields = line.replace('\r','').replace('\n','')
#     fields=tmp_fields.split('\t')
#     print(fields)
#     if len(fields) != 3:
#         continue
#     timestamp = float(fields[1])
#     timestamp /= 1e9
#     timestamps.append(timestamp)
# print(timestamps)
# while True:
#     print(subprocess.check_output('adb shell service call SurfaceFlinger 1013',shell=True))
